<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs_listing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_type_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->boolean('remote')->default(false);
            $table->enum('status', ['draft', 'published', 'expired'])->default('draft');
            $table->boolean('is_open')->default(true); // New field: true = open, false = closed
            $table->boolean('is_featured')->default(false); // For paid featured jobs
            $table->string('application_method')->default('form'); // 'form' or 'external'
            $table->string('external_link')->nullable(); // For external applications
            $table->string('slug')->unique(); // SEO-friendly URL
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'is_open', 'is_featured', 'category_id', 'job_type_id', 'title', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs_listing', function (Blueprint $table) {
            //
        });
    }
};
