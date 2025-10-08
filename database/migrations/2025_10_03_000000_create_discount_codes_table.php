<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('discount_codes', function (Blueprint $table) {
      $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
      $table->string('name')->nullable();
      $table->string('code');
      $table->integer('max_usage_count')->default(0);
      $table->integer('current_usage_count')->default(0);
      $table->double('discount')->default(0);
      $table->timestampTz('start_date')->nullable();
      $table->timestampTz('end_date')->nullable();
      $table->jsonb('challenge_amount')->nullable();
      $table->jsonb('challenge_step')->nullable();
      $table->jsonb('email')->nullable();
      $table->uuid('created_by')->nullable();
      $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->string('type')->default('admin');
      $table->double('commission_percentage')->default(0);
      $table->timestamp('deleted_at')->nullable();
      $table->string('status')->default('active');

      // Add indexes
      $table->index('code');
      $table->index('created_by');
      $table->index('status');
    });

    // Add foreign key constraint for created_by
    Schema::table('discount_codes', function (Blueprint $table) {
      $table->foreign('created_by')->references('uuid')->on('users')->onDelete('set null');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('discount_codes');
  }
};
