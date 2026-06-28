<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('is_active');
            $table->string('admin_contact_name')->nullable()->after('approval_status');
            $table->string('admin_contact_phone')->nullable()->after('admin_contact_name');
            $table->text('rejection_reason')->nullable()->after('admin_contact_phone');
            $table->timestamp('reviewed_at')->nullable()->after('rejection_reason');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('school_admin_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 15);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_admin_invites');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn([
                'approval_status',
                'admin_contact_name',
                'admin_contact_phone',
                'rejection_reason',
                'reviewed_at',
            ]);
        });
    }
};
