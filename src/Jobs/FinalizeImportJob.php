<?php

namespace UsmanAhmed\ImportExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use UsmanAhmed\ImportExport\Contracts\ContextRestorer;
use UsmanAhmed\ImportExport\Enums\FailureStage;
use UsmanAhmed\ImportExport\Enums\ImportStatus;
use UsmanAhmed\ImportExport\Events\ImportFailed;
use UsmanAhmed\ImportExport\Models\Import;
use UsmanAhmed\ImportExport\Services\ErrorReportDispatcher;
use UsmanAhmed\ImportExport\Services\FinalizeImport;
use UsmanAhmed\ImportExport\Services\ImportLeaseManager;
use UsmanAhmed\ImportExport\State\ImportStateMachine;
use Throwable;

final class FinalizeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    /** @param array<string, scalar|null> $context */
    public function __construct(public string $importId, public array $context)
    {
        $this->tries = (int) config('bulk-imports.queue.tries', 3);
        $this->timeout = (int) config('bulk-imports.queue.finalizer_timeout', 900);
    }

    public function backoff(): array
    {
        return config('bulk-imports.queue.backoff', [10, 30, 90]);
    }

    public function handle(ContextRestorer $restorer, ImportLeaseManager $leases, FinalizeImport $finalizer): void
    {
        $restorer->run($this->context, function () use ($leases, $finalizer): void {
            $token = (string) Str::uuid();
            $import = $leases->claimFinalization($this->importId, $token);
            if ($import === null) {
                return;
            }

            try {
                $finalizer->handle($import, $token);
            } catch (Throwable $exception) {
                $leases->release($this->importId, $token);
                throw $exception;
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        app(ContextRestorer::class)->run($this->context, function () use ($exception): void {
            $import = Import::query()->find($this->importId);
            if ($import === null || $import->status->isTerminal()) {
                return;
            }

            $reason = Str::limit($exception?->getMessage() ?? 'Finalization failed.', 4000);
            $import = app(ImportStateMachine::class)->transition($import, ImportStatus::FAILED, [
                'failure_stage' => FailureStage::FINALIZATION->value,
                'error_message' => $reason,
                'failed_at' => now(),
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);
            app(ErrorReportDispatcher::class)->dispatch($import);
            ImportFailed::dispatch($import, $reason);
        });
    }
}
