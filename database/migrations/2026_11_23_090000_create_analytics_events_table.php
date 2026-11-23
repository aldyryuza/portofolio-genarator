<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-party, cookie-less analytics. No IP addresses or user agents are
     * stored: visitor_hash is a keyed hash that changes every day.
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('path', 255);
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resume_id')->nullable()->constrained()->nullOnDelete();
            $table->string('referrer_host', 100)->nullable();
            $table->string('source', 30)->nullable();
            $table->string('device', 10);
            $table->string('visitor_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
