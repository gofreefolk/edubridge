<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('registration_number');
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'registration_number']);
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('direction')->default('pickup'); // pickup, drop, both
            $table->time('default_start_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->time('scheduled_time')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('student_route_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'route_id']);
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('trip_date');
            $table->string('status')->default('scheduled'); // scheduled, in_progress, completed, cancelled
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('delay_note')->nullable();
            $table->timestamps();
        });

        Schema::create('boarding_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // boarded, alighted, absent
            $table->timestamp('logged_at');
            $table->timestamps();
        });

        Schema::create('transport_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('absence_date');
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'absence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_absences');
        Schema::dropIfExists('boarding_logs');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('student_route_assignments');
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('vehicles');
    }
};
