<?php

namespace UsmanAhmed\ImportExport\Services;

use Illuminate\Database\DatabaseManager;
use UsmanAhmed\ImportExport\Data\ImportContext;
use UsmanAhmed\ImportExport\Data\RowFailure;
use UsmanAhmed\ImportExport\Enums\ChunkStatus;
use UsmanAhmed\ImportExport\Enums\FailureStage;
use UsmanAhmed\ImportExport\Enums\ImportMode;
use UsmanAhmed\ImportExport\Enums\ImportStatus;
use UsmanAhmed\ImportExport\Events\ImportCompleted;
use UsmanAhmed\ImportExport\Events\ImportCompletedWithErrors;
use UsmanAhmed\ImportExport\Events\ImportFailed;
use UsmanAhmed\ImportExport\Exceptions\AtomicCommitRejected;
use UsmanAhmed\ImportExport\Models\Import;
use UsmanAhmed\ImportExport\Models\ImportChunk;
use UsmanAhmed\ImportExport\Models\ImportFailure;
use UsmanAhmed\ImportExport\Models\ImportStagedRow;
use UsmanAhmed\ImportExport\State\ImportStateMachine;
use RuntimeException;

final class FinalizeImport
{
    public function __construct(
        private readonly ImportDefinitionRegistry $definitions,
        private readonly ImportStateMachine $states,
        private readonly RowFailureRecorder $failureRecorder,
        private readonly DatabaseManager $database,
        private readonly ConnectionGuard $connections,
        private readonly ErrorReportDispatcher $reports,
    ) {}

    public function handle(Import $import, string $leaseToken): void
    {
        $counts = ImportChunk::query()
            ->where('import_id', $import->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        if ((int) ($counts[ChunkStatus::PROCESSING->value] ?? 0) > 0
            || (int) ($counts[ChunkStatus::QUEUED->value] ?? 0) > 0) {
            throw new RuntimeException('Finalization cannot run while chunks are still pending.');
        }

        if ((int) ($counts[ChunkStatus::FAILED->value] ?? 0) > 0) {
            $message = 'One or more chunks exhausted their retry budget.';
            $import = $this->states->transition($import, ImportStatus::FAILED, [
                'failure_stage' => FailureStage::CHUNK->value,
                'failure_type' => 'persistence_error',
                'error_message' => $message,
                'failed_at' => now(),
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);
            $this->reports->dispatch($import);
            ImportFailed::dispatch($import, $message);

            return;
        }

        if ($import->mode === ImportMode::ATOMIC) {
            $this->finalizeAtomic($import, $leaseToken);

            return;
        }

        $status = $import->failed_rows > 0
            ? ImportStatus::COMPLETED_WITH_ERRORS
            : ImportStatus::COMPLETED;
        $import = $this->states->transition($import, $status, [
            'completed_at' => now(),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);
        $this->dispatchTerminalEventsAndReport($import);
    }

    private function finalizeAtomic(Import $import, string $leaseToken): void
    {
        if ($import->failed_rows > 0) {
            $import = $this->states->transition($import, ImportStatus::COMPLETED_WITH_ERRORS, [
                'completed_at' => now(),
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);
            $this->cleanStaging($import);
            $this->dispatchTerminalEventsAndReport($import);

            return;
        }

        $definition = $this->definitions->make($import->type);
        $committer = $definition->atomicCommitter();
        if ($committer === null) {
            throw new RuntimeException('Atomic import has no committer.');
        }

        $context = new ImportContext(
            $import->id,
            $import->actor_type,
            $import->actor_id,
            $import->context ?? [],
            $import->options ?? [],
        );
        $connection = $this->connections->assertMetadataAndTargetMatch(
            $committer->connectionName($definition, $context),
        );

        try {
            $import = $this->database->connection($connection)->transaction(function () use (
                $import,
                $leaseToken,
                $committer,
                $definition,
                $context,
            ): Import {
                $current = Import::query()
                    ->whereKey($import->id)
                    ->where('lease_token', $leaseToken)
                    ->firstOrFail();
                $result = $committer->commit($current, $definition, $context);

                if ($result->succeeded + $result->skipped !== $current->staged_rows) {
                    throw new RuntimeException('Atomic committer accounting does not match staged rows.');
                }

                $current->forceFill([
                    'succeeded_rows' => $result->succeeded,
                    'skipped_rows' => $result->skipped,
                    'staged_rows' => 0,
                ])->save();

                $completed = $this->states->transition($current->refresh(), ImportStatus::COMPLETED, [
                    'completed_at' => now(),
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ]);
                $this->cleanStaging($completed);

                return $completed;
            }, attempts: 3);
        } catch (AtomicCommitRejected $exception) {
            $import = $this->database->connection($connection)->transaction(function () use (
                $import,
                $leaseToken,
                $exception,
            ): Import {
                $current = Import::query()
                    ->whereKey($import->id)
                    ->where('lease_token', $leaseToken)
                    ->firstOrFail();
                ImportFailure::query()->where('import_id', $current->id)->delete();
                $this->recordAtomicRejections($current, $exception);
                $failedRows = collect($exception->failures)->pluck('row_number')->unique()->count();
                $current->forceFill(['failed_rows' => $failedRows])->save();
                $completed = $this->states->transition(
                    $current->refresh(),
                    ImportStatus::COMPLETED_WITH_ERRORS,
                    [
                        'completed_at' => now(),
                        'lease_token' => null,
                        'lease_expires_at' => null,
                    ],
                );
                $this->cleanStaging($completed);

                return $completed;
            }, attempts: 3);
        }

        $this->dispatchTerminalEventsAndReport($import);
    }

    private function recordAtomicRejections(Import $import, AtomicCommitRejected $exception): void
    {
        foreach (collect($exception->failures)->groupBy('chunk_id') as $chunkId => $failures) {
            $this->failureRecorder->record(
                $import->id,
                (string) $chunkId,
                $failures->map(static fn (array $failure): RowFailure => new RowFailure(
                    $failure['row_number'],
                    $failure['column'],
                    $failure['code'],
                    $failure['message'],
                    $failure['row'],
                ))->all(),
            );
        }
    }

    private function cleanStaging(Import $import): void
    {
        if (config('bulk-imports.atomic.cleanup_staging_after_completion', true)) {
            ImportStagedRow::query()->where('import_id', $import->id)->delete();
        }
    }

    private function dispatchTerminalEventsAndReport(Import $import): void
    {
        $this->reports->dispatch($import);

        if ($import->status === ImportStatus::COMPLETED) {
            ImportCompleted::dispatch($import);
        } elseif ($import->status === ImportStatus::COMPLETED_WITH_ERRORS) {
            ImportCompletedWithErrors::dispatch($import);
        }
    }
}
