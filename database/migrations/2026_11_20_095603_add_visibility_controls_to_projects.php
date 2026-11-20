<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner-controlled redaction: hide the client name, keep internal notes
     * (infrastructure, architecture) private, and mark gallery images confidential.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('hide_client')->default(false)->after('client_label');
            $table->text('confidential_notes')->nullable()->after('outcome');
        });

        Schema::table('project_media', function (Blueprint $table) {
            $table->boolean('is_confidential')->default(false)->after('caption');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_media', function (Blueprint $table) {
            $table->dropColumn('is_confidential');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['hide_client', 'confidential_notes']);
        });
    }
};
