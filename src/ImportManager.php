<?php

namespace UsmanAhmed\ImportExport;

use Illuminate\Http\UploadedFile;
use UsmanAhmed\ImportExport\Contracts\ImportAuthorizer;
use UsmanAhmed\ImportExport\Enums\IdempotencyStrategy;
use UsmanAhmed\ImportExport\Models\Import;
use UsmanAhmed\ImportExport\Services\CancelImport;
use UsmanAhmed\ImportExport\Services\DataAccess;
use UsmanAhmed\ImportExport\Services\ImportDispatcher;
use UsmanAhmed\ImportExport\Services\RetryImport;

final class ImportManager
{
    public function __construct(private readonly DataAccess $access, private readonly ImportDispatcher $dispatcher) {}

    public function dispatch(string $resource, UploadedFile $file, object $actor, array $options = [], ?IdempotencyStrategy $idempotency = null): Import
    {
        $definition = $this->access->definition($resource);
        $this->access->assertActor($definition, $actor, $resource);
        $this->access->check($definition->canImport($actor));

        return $this->dispatcher->dispatchDefinition($definition::class, $file, $actor, $definition->context(), $options, $idempotency);
    }

    public function retry(Import $import, object $actor): Import
    {
        $this->access->check(app(ImportAuthorizer::class)->canRetry($actor, $import));

        return app(RetryImport::class)->handle($import);
    }

    public function cancel(Import $import, object $actor): Import
    {
        $this->access->check(app(ImportAuthorizer::class)->canCancel($actor, $import));

        return app(CancelImport::class)->handle($import);
    }
}
