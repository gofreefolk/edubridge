<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b online fee payment (Razorpay payment links, paid into each school's own
 * account) and Phase 1 adoption tracking (when a user last opened the app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable();
        });

        // One gateway account per school. Secrets are stored encrypted (model casts).
        Schema::create('fee_gateway_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('razorpay');
            $table->string('key_id', 100);
            $table->text('key_secret');
            $table->text('webhook_secret')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fee_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('razorpay');
            $table->string('gateway_link_id', 100);
            $table->string('short_url');
            $table->unsignedBigInteger('amount_paise');
            $table->string('status', 20)->default('created'); // created, paid, cancelled, expired
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('fee_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'gateway_link_id']);
            $table->index(['fee_invoice_id', 'status']);
        });

        // Unique gateway payment id makes webhook / callback retries idempotent.
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->string('gateway_payment_id', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_payment_id']);
            $table->dropColumn('gateway_payment_id');
        });
        Schema::dropIfExists('fee_payment_links');
        Schema::dropIfExists('fee_gateway_accounts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
