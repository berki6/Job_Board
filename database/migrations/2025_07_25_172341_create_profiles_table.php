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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable(); // Job seeker bio
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('company_name')->nullable(); // Employer only
            $table->string('logo_path')->nullable(); // Employer logo, processed by Intervention Image
            $table->string('resume_path')->nullable(); // For job seekers // PDF, stored via Filesystem
            $table->json('skills')->nullable(); // User skills as JSON array
            $table->timestamps();
            $table->index('user_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            //
        });
    }
};
