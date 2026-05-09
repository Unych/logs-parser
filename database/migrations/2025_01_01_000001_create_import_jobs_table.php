<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->char('file_hash', 64)->unique();
            $table->string('original_name', 255);
            $table->string('stored_path', 512);
            $table->unsignedBigInteger('file_size');
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])
                  ->default('pending')
                  ->index();
            $table->unsignedBigInteger('lines_processed')->default(0);
            $table->unsignedBigInteger('lines_invalid')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
