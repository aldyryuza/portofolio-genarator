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
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('headline', 160)->nullable();
            $table->text('summary')->nullable();
            $table->text('biography')->nullable();
            $table->string('location_public', 120)->nullable();
            $table->string('public_email')->nullable();
            // Foreign key to media_assets is added in M3 once that table exists.
            $table->unsignedBigInteger('avatar_media_id')->nullable();
            $table->string('availability_status', 32)->default('not_available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
