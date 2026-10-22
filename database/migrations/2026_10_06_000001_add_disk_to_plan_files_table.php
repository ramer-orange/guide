<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_files', function (Blueprint $table) {
            $table->string('disk')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        if (DB::table('plan_files')->whereNotNull('disk')->exists()) {
            throw new RuntimeException('Cannot remove plan_files.disk while attachment rows reference specific storage disks.');
        }

        Schema::table('plan_files', function (Blueprint $table) {
            $table->dropColumn('disk');
        });
    }
};
