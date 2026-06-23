<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('title_en')->nullable();
            $table->text('body_en')->nullable();
            $table->string('priority')->default('normal'); // normal, urgent
            $table->string('audience_type')->default('whole_school'); // whole_school, class, section, smc_only
            $table->timestamp('pinned_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('magic_link_token')->nullable()->unique();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status', 'published_at']);
            $table->index(['school_id', 'priority']);
        });

        Schema::create('notice_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['notice_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notice_audiences');
        Schema::dropIfExists('notices');
    }
};
