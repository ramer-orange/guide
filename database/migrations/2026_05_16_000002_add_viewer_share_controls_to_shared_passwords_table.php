<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_passwords', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('shared_password');
            $table->timestamp('disabled_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('shared_passwords', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'disabled_at']);
        });
    }
};
