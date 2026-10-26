<?php

namespace UsmanAhmed\ImportExport\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use UsmanAhmed\ImportExport\Enums\ChunkStatus;
use UsmanAhmed\ImportExport\Enums\ImportStatus;
use UsmanAhmed\ImportExport\Examples\Models\Student;
use UsmanAhmed\ImportExport\Facades\BulkImport;
use UsmanAhmed\ImportExport\Jobs\FinalizeImportJob;
use UsmanAhmed\ImportExport\Jobs\PrepareImportJob;
use UsmanAhmed\ImportExport\Jobs\ProcessImportChunkJob;
use UsmanAhmed\ImportExport\Models\ImportChunk;
use UsmanAhmed\ImportExport\Services\RetryImport;
use UsmanAhmed\ImportExport\Tests\Support\FlakyStudentsImport;
use UsmanAhmed\ImportExport\Tests\TestCase;
use RuntimeException;

final class ChunkRetryTest extends TestCase
{
    public function test_database_failure_rolls_back_then_retry_processes_only_the_failed_chunk_once(): void
    {
        Queue::fake();
        FlakyStudentsImport::$failNextBatch = true;
        config()->set('bulk-imports.definitions.flaky_students', FlakyStudentsImport::class);
        $this->seedCourses();
        $import = BulkImport::make(FlakyStudentsImport::class)
            ->file($this->uploadedCsv([
                ['STU-1', 'One', 'one@example.test', '+201000000001', '2000-01-01', 'COURSE-01'],
            ]))
            ->by($this->createActor())
            ->dispatch();

        app()->call([new PrepareImportJob($import->id, []), 'handle']);
        $chunk = ImportChunk::query()->where('import_id', $import->id)->firstOrFail();

        try {
            app()->call([new ProcessImportChunkJob($chunk->id, []), 'handle']);
            self::fail('The first write should simulate a disconnect.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated database disconnect.', $exception->getMessage());
        }

        self::assertSame(ChunkStatus::FAILED, $chunk->refresh()->status);
        self::assertSame(0, Student::query()->count());
        self::assertSame(0, $import->refresh()->processed_rows);

        app()->call([new FinalizeImportJob($import->id, []), 'handle']);
        self::assertSame(ImportStatus::FAILED, $import->refresh()->status);

        app(RetryImport::class)->handle($import);
        self::assertSame(ChunkStatus::QUEUED, $chunk->refresh()->status);
        self::assertSame(ImportStatus::PROCESSING, $import->refresh()->status);

        app()->call([new ProcessImportChunkJob($chunk->id, []), 'handle']);
        app()->call([new FinalizeImportJob($import->id, []), 'handle']);

        self::assertSame(ImportStatus::COMPLETED, $import->refresh()->status);
        self::assertSame(ChunkStatus::COMPLETED, $chunk->refresh()->status);
        self::assertSame(2, $chunk->attempts);
        self::assertSame(1, Student::query()->count());
        self::assertSame(1, $import->succeeded_rows);
    }
}
