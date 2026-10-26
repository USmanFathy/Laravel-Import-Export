<?php

namespace UsmanAhmed\ImportExport\Services;

use UsmanAhmed\ImportExport\Enums\ChunkStatus;
use UsmanAhmed\ImportExport\Enums\FailureStage;
use UsmanAhmed\ImportExport\Enums\ImportStatus;
use UsmanAhmed\ImportExport\Events\ImportQueued;
use UsmanAhmed\ImportExport\Exceptions\InvalidStateTransition;
use UsmanAhmed\ImportExport\Jobs\PrepareImportJob;
use UsmanAhmed\ImportExport\Models\Import;
use UsmanAhmed\ImportExport\Models\ImportChunk;
use UsmanAhmed\ImportExport\State\ImportStateMachine;
use Throwable;

final class RetryImport
{
    public function __construct(
        private readonly ImportStateMachine $states,
        private readonly ChunkBatchDispatcher $batches,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->status !== ImportStatus::FAILED) {
            throw new InvalidStateTransition('Only a failed import can be retried.');
        }
        if ($import->files_cleanup_started_at !== null || $import->files_deleted_at !== null) {
            throw new InvalidStateTransition('This import can no longer be retried because its retained source/chunks were deleted.');
        }

        $stage = $import->failure_stage;
        $import = $this->states->transition(
            $import,
            ImportStatus::QUEUED,
            [
                'failed_at' => null,
                'error_message' => null,
                'failure_stage' => null,
                'preparation_dispatched_at' => null,
                'queue_batch_id' => null,
                'batch_dispatched_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
            ],
            static fn ($query) => $query
                ->whereNull('files_cleanup_started_at')
                ->whereNull('files_deleted_at'),
        );
        try {
            ImportQueued::dispatch($import);
        } catch (Throwable $exception) {
            $this->states->transition($import->refresh(), ImportStatus::FAILED, [
                'failure_stage' => $stage?->value,
                'error_message' => str($exception->getMessage())->limit(4000)->toString(),
                'failed_at' => now(),
            ]);
            throw $exception;
        }

        if ($stage === FailureStage::PREPARATION || $import->total_chunks === 0) {
            $pending = PrepareImportJob::dispatch($import->id, $import->context ?? []);
            if (($connection = config('bulk-imports.queue.connection')) !== null) {
                $pending->onConnection($connection);
            }
            $pending->onQueue((string) config('bulk-imports.queue.name', 'imports'));
            unset($pending);
            Import::query()->whereKey($import->id)->update([
                'preparation_dispatched_at' => now(),
                'updated_at' => now(),
            ]);

            return $import->refresh();
        }

        $failedChunkIds = ImportChunk::query()
            ->where('import_id', $import->id)
            ->where('status', ChunkStatus::FAILED->value)
            ->orderBy('chunk_number')
            ->pluck('id')
            ->all();

        if ($stage === FailureStage::CHUNK && $failedChunkIds !== []) {
            ImportChunk::query()->whereIn('id', $failedChunkIds)->update([
                'status' => ChunkStatus::QUEUED->value,
                'failed_at' => null,
                'error_message' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'updated_at' => now(),
            ]);
        }

        $import = $this->states->transition($import, ImportStatus::VALIDATING);
        $import = $this->states->transition($import, ImportStatus::PROCESSING);

        if ($stage === FailureStage::FINALIZATION || $failedChunkIds === []) {
            $this->batches->dispatch($import, []);
        } else {
            $this->batches->dispatch($import, $failedChunkIds);
        }

        return $import->refresh();
    }
}
