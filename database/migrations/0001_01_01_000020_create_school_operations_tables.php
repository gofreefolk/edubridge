<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 school operations: richer student profiles with contacts / authorised
 * pickups, absence alerts, centre log, and operational checklists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('blood_group', 5)->nullable()->after('gender');
            $table->text('allergies')->nullable()->after('blood_group');
            $table->text('medical_notes')->nullable()->after('allergies');
            $table->text('address')->nullable()->after('medical_notes');
        });

        Schema::create('student_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 50);
            $table->string('phone', 20);
            $table->boolean('is_emergency')->default(true);
            $table->boolean('can_pickup')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('student_id');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestamp('absence_alert_sent_at')->nullable()->after('note');
        });

        Schema::create('centre_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 30); // daily, incident, health, behaviour, other
            $table->string('title');
            $table->text('body');
            $table->timestamp('occurred_at');
            $table->boolean('visible_to_parents')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'occurred_at']);
            $table->index(['student_id', 'occurred_at']);
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('frequency', 10)->default('daily'); // daily, weekly
            $table->json('items'); // list of item labels
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'is_active']);
        });

        Schema::create('checklist_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_date'); // the day, or the Monday of the week
            $table->json('responses'); // [{item, done, note}]
            $table->unsignedSmallInteger('done_count');
            $table->unsignedSmallInteger('total_count');
            $table->timestamps();

            $table->unique(['checklist_template_id', 'period_date']);
            $table->index(['school_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_submissions');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('centre_logs');
        Schema::dropIfExists('student_contacts');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('absence_alert_sent_at');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['gender', 'blood_group', 'allergies', 'medical_notes', 'address']);
        });
    }
};
