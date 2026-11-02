<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No soft delete: projects are never deleted from the interface.
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);                        
            $table->string('link', 2048)->nullable();          
            $table->text('note')->nullable();              
            $table->string('status', 20)->default('disabled'); 
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};