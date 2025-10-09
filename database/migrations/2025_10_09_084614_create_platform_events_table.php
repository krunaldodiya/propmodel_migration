<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_events', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_uuid');
            $table->string('event');
            $table->timestampsTz();
            $table->text('reason')->nullable();
            $table->uuid('platform_account_uuid')->nullable();

            // Foreign key constraints
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->foreign('platform_account_uuid')->references('uuid')->on('platform_accounts')->onDelete('set null');

            // Indexes
            $table->index('user_uuid');
            $table->index('event');
            $table->index('platform_account_uuid');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_events');
    }
};
