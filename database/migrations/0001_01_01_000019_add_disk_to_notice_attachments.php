<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notice_attachments', function (Blueprint $table) {
            $table->string('disk', 32)->default('public')->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('notice_attachments', function (Blueprint $table) {
            $table->dropColumn('disk');
        });
    }
};
