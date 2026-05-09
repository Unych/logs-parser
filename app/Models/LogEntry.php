<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogEntry extends Model
{
    protected $fillable = [
        'ip',
        'requested_at',
        'url_hash',
        'http_status',
        'ua_hash',
        'method',
        'url',
        'response_size',
        'referer',
        'user_agent',
        'os',
        'arch',
        'browser',
        'is_bot',
        'bot_name',
        'import_job_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'http_status' => 'integer',
        'response_size' => 'integer',
        'is_bot' => 'boolean',
        'import_job_id' => 'integer',
    ];

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
