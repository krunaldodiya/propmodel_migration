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
        Schema::create('breach_account_activities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->integer('breach_count');
            $table->timestampTz('last_breach_date');
            $table->boolean('is_breached');
            $table->uuid('platform_account_uuid')->nullable();

            // Foreign key constraint
            $table->foreign('platform_account_uuid')->references('uuid')->on('platform_accounts')->onDelete('set null');

            // Indexes
            $table->index('platform_account_uuid');
            $table->index('is_breached');
            $table->index('last_breach_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('breach_account_activities');
    }
};
