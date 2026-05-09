<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'file_hash',
        'original_name',
        'stored_path',
        'file_size',
        'status',
        'lines_processed',
        'lines_invalid',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'lines_processed' => 'integer',
        'lines_invalid' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LogEntry::class, 'import_job_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
