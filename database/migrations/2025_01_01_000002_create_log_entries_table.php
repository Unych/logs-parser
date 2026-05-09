<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_entries', function (Blueprint $table) {
            $table->id();

            $table->string('ip', 45);
            $table->dateTime('requested_at');
            $table->char('url_hash', 32);
            $table->unsignedSmallInteger('http_status');
            $table->char('ua_hash', 32);

            $table->string('method', 16);
            $table->text('url');
            $table->unsignedBigInteger('response_size')->nullable();
            $table->text('referer')->nullable();
            $table->text('user_agent')->nullable();

            $table->string('os', 32)->default('other');
            $table->string('arch', 16)->default('unknown');
            $table->string('browser', 32)->default('other');
            $table->boolean('is_bot')->default(false);
            $table->string('bot_name', 64)->nullable();

            $table->foreignId('import_job_id')
                  ->nullable()
                  ->constrained('import_jobs')
                  ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['requested_at', 'ip', 'url_hash', 'http_status', 'ua_hash'],
                'log_entries_dedup_unique'
            );

            $table->index('requested_at', 'log_entries_requested_at_idx');
            $table->index(['requested_at', 'is_bot'], 'log_entries_date_bot_idx');
            $table->index(['requested_at', 'os'], 'log_entries_date_os_idx');
            $table->index(['requested_at', 'arch'], 'log_entries_date_arch_idx');
            $table->index(['requested_at', 'browser'], 'log_entries_date_browser_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
