<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category'); // homework, leave, concern, smc_grievance, behaviour, other
            $table->string('subject');
            $table->string('status')->default('open'); // open, acknowledged, resolved
            $table->string('direction'); // parent_to_teacher, parent_to_smc, teacher_to_parent
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('smc_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role'); // parent_rep, teacher_rep, lsg_rep, headmaster, other
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('smc_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled
            $table->timestamps();
        });

        Schema::create('smc_development_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('planned'); // planned, in_progress, completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smc_development_items');
        Schema::dropIfExists('smc_meetings');
        Schema::dropIfExists('smc_members');
        Schema::dropIfExists('feedback_messages');
        Schema::dropIfExists('feedback_threads');
    }
};
