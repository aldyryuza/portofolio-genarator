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
        Schema::create('export_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('format', 10);
            $table->json('configuration_json');
            $table->string('template_key', 30);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            // Route key for owner-only downloads.
            $table->uuid('uuid')->unique();
            $table->foreignId('export_preset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 10);
            $table->string('state', 20)->default('pending');
            $table->json('configuration_json');
            $table->string('output_disk', 32)->nullable();
            $table->string('output_path')->nullable();
            $table->unsignedBigInteger('output_bytes')->nullable();
            $table->string('failed_reason', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('export_presets');
    }
};
