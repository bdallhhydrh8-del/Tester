<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            // fixed order inside the project
            $table->foreignId('project_id')->constrained('projects');
            $table->unsignedInteger('position');              
            $table->text('instructions');
            // yes / no
            $table->boolean('ask_success')->default(true);    
            $table->boolean('ask_difficulty')->default(true); 
            $table->boolean('ask_notes')->default(true);
            $table->boolean('ask_file')->default(false);      
            $table->boolean('required')->default(true);
            // extra questions, such as checkbox lists
            $table->json('custom_questions')->nullable(); 
            // old responses can still point to the card
            $table->softDeletes();                            
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};