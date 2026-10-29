<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tester_id')->constrained('users');

            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('total_size');       // bytes
            $table->unsignedSmallInteger('total_chunks');

            // JSON list of chunk indexes already received, e.g. [0,1,3].
            // The default ('[]') is set on the model, because MySQL does not
            // allow a default value on JSON columns in older versions.
            $table->json('received_chunks');

            $table->enum('status', ['open', 'completed', 'expired'])->default('open');
            $table->string('temp_path');                    // folder holding the chunks

            // Set when the upload is assembled. Makes "complete" safe to call twice.
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();

            $table->timestamps();

            // Cleanup of stale sessions.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};