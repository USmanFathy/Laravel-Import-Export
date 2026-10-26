<?php

namespace UsmanAhmed\ImportExport\Services;

use Illuminate\Support\Facades\Bus;
use UsmanAhmed\ImportExport\Enums\ChunkStatus;
use UsmanAhmed\ImportExport\Enums\ImportStatus;
use UsmanAhmed\ImportExport\Events\ImportCancelled;
use UsmanAhmed\ImportExport\Exceptions\InvalidStateTransition;
use UsmanAhmed\ImportExport\Models\Import;
use UsmanAhmed\ImportExport\Models\ImportChunk;
use UsmanAhmed\ImportExport\State\ImportStateMachine;
use Throwable;

final class CancelImport
{
    public function __construct(
        private readonly ImportStateMachine $states,
        private readonly ErrorReportDispatcher $reports,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->status->isTerminal()) {
            if ($import->status === ImportStatus::CANCELLED) {
                return $import;
            }

            throw new InvalidStateTransition("A {$import->status->value} import cannot be cancelled.");
        }

        Import::query()->whereKey($import->id)->update(['cancel_requested_at' => now(), 'updated_at' => now()]);

        if ($import->queue_batch_id !== null) {
            try {
                Bus::findBatch($import->queue_batch_id)?->cancel();
            } catch (Throwable $exception) {
                // Durable import/chunk cancellation remains authoritative even if
                // Laravel's optimization batch record is temporarily unavailable.
                report($exception);
            }
        }

        ImportChunk::query()
            ->where('import_id', $import->id)
            ->whereIn('status', [ChunkStatus::QUEUED->value, ChunkStatus::FAILED->value])
            ->update([
                'status' => ChunkStatus::CANCELLED->value,
                'completed_at' => now(),
                'lease_token' => null,
                'lease_expires_at' => null,
                'updated_at' => now(),
            ]);

        $import = $this->states->transition($import->refresh(), ImportStatus::CANCELLED, [
            'cancel_requested_at' => now(),
            'cancelled_at' => now(),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);
        $this->reports->dispatch($import);
        ImportCancelled::dispatch($import);

        return $import;
    }
}
