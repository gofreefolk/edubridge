<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->timestamp('scheduled_publish_at')->nullable()->after('published_at');
            $table->timestamp('whatsapp_sent_at')->nullable()->after('scheduled_publish_at');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['scheduled_publish_at', 'whatsapp_sent_at']);
        });
    }
};
