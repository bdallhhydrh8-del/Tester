<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: a response or an issue. Both columns stay
            // empty until the file is attached. Also adds the
            // (fileable_type, fileable_id) index.
            $table->nullableMorphs('fileable');

            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('path');               // random name on the private disk
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');   // bytes
            $table->timestamps();

            // Retention job and "unattached older than one day" cleanup.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};