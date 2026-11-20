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
        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('label', 60)->nullable();
            $table->string('url', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['profile_id', 'is_visible', 'sort_order']);
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->string('company', 160);
            $table->string('title', 160);
            $table->string('employment_type', 30);
            $table->string('location', 120)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->json('achievements_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index('start_date');
            $table->index(['is_published', 'is_current']);
        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->string('institution', 160);
            $table->string('degree', 160)->nullable();
            $table->string('field_of_study', 160)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'end_date']);
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('issuer', 160);
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->string('credential_id', 120)->nullable();
            $table->string('credential_url', 500)->nullable();
            $table->foreignId('file_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'issue_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('educations');
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('social_links');
    }
};
