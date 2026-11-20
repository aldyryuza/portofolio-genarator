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
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            // Public route key for downloads; numeric ids are not exposed.
            $table->uuid('uuid')->unique();
            $table->string('title', 120);
            $table->string('language', 10)->default('en');
            $table->string('version', 30);
            $table->foreignId('file_media_id')->constrained('media_assets')->restrictOnDelete();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_public', 'is_default']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('sender_name', 120);
            $table->string('sender_email');
            $table->string('subject', 160)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('new');
            $table->timestamp('read_at')->nullable();
            // HMAC of the sender IP: enough to spot abuse, without storing the address.
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('resumes');
    }
};
