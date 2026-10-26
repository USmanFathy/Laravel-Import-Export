<?php

namespace UsmanAhmed\ImportExport\Models;

use Illuminate\Database\Eloquent\Model;
use UsmanAhmed\ImportExport\Models\Concerns\UsesBulkImportConnection;

/**
 * @property int $id
 * @property string $export_id
 * @property int $part_number
 * @property string $file_path
 * @property string $checksum
 * @property int $rows
 */
class ExportPart extends Model
{
    use UsesBulkImportConnection;

    protected $table = 'data_export_parts';

    protected $guarded = [];

    public $timestamps = false;
}
