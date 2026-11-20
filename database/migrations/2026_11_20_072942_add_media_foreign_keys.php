<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referenced media may not be hard-deleted; the app also blocks soft deletes.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('cover_media_id')->references('id')->on('media_assets')->restrictOnDelete();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreign('avatar_media_id')->references('id')->on('media_assets')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['cover_media_id']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['avatar_media_id']);
        });
    }
};
