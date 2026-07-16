<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('id');
            $table->string('avatar')->nullable()->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
            $table->unique('google_id');
        });

        Schema::table('shared_passwords', function (Blueprint $table) {
            $table->unsignedInteger('access_version')->default(1)->after('disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('shared_passwords', function (Blueprint $table) {
            $table->dropColumn('access_version');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->string('password')->nullable()->after('email_verified_at');
            $table->dropColumn(['google_id', 'avatar']);
        });
    }
};
