<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 fee management. Amounts are integer paise. Invoices and payments are never
 * deleted: they are voided with a reason so the books always reconcile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100); // Tuition, Bus, PTA, Books…
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->cascadeOnDelete(); // null = every class
            $table->foreignId('fee_head_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100); // billing period, e.g. "Term 1"; one invoice per student per label
            $table->unsignedBigInteger('amount_paise');
            $table->date('due_on');
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'label']);
        });

        Schema::create('fee_concessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained()->cascadeOnDelete(); // null = whole invoice
            $table->string('type', 10); // percent, amount
            $table->unsignedBigInteger('value'); // percent 1–100, or paise
            $table->string('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('student_id');
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('number', 60);
            $table->string('label', 100);
            $table->date('issued_on');
            $table->date('due_on');
            $table->unsignedBigInteger('total_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->unsignedBigInteger('paid_paise')->default(0);
            $table->string('status', 20)->default('issued'); // issued, partially_paid, paid, void
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'number']);
            $table->index(['school_id', 'status', 'due_on']);
            $table->index(['student_id', 'academic_year_id', 'label']);
        });

        Schema::create('fee_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->timestamps();
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 60);
            $table->unsignedBigInteger('amount_paise');
            $table->string('method', 20); // cash, upi, bank, cheque, online
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'receipt_number']);
            $table->index(['school_id', 'paid_at']);
        });

        // Per-school, per-document numbering: format, reset rule and the running counter.
        Schema::create('fee_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // invoice, receipt
            $table->string('format', 60);
            $table->string('reset', 20)->default('academic_year'); // never, yearly, academic_year
            $table->string('period', 30)->nullable(); // period the counter currently belongs to
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_number_sequences');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoice_lines');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('fee_concessions');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_heads');
    }
};
