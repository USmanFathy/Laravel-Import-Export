<?php

namespace UsmanAhmed\ImportExport\Tests\Support;

use UsmanAhmed\ImportExport\Data\ImportContext;
use UsmanAhmed\ImportExport\Data\ResolvedReferences;
use UsmanAhmed\ImportExport\Definitions\EloquentTarget;
use UsmanAhmed\ImportExport\Definitions\ImportDefinition;
use UsmanAhmed\ImportExport\Enums\DuplicateStrategy;

final class TenantStudentsImport extends ImportDefinition
{
    public function columns(): array
    {
        return ['student_code', 'name'];
    }

    public function rules(): array
    {
        return ['student_code' => ['required', 'string'], 'name' => ['required', 'string']];
    }

    public function uniqueBy(): array
    {
        return ['student_code'];
    }

    public function targetUniqueBy(): array
    {
        return ['organization_id', 'student_code'];
    }

    public function duplicateStrategy(): DuplicateStrategy
    {
        return DuplicateStrategy::UPSERT;
    }

    public function target(): EloquentTarget
    {
        return EloquentTarget::for(TenantStudent::class, ['name', 'updated_at']);
    }

    public function transform(
        array $row,
        ResolvedReferences $references,
        ImportContext $context,
    ): array {
        $row['organization_id'] = (int) $context->contextValue('organization_id');

        return $row;
    }
}
