<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_types', function ($table) {
            $table->dropUnique('template_types_template_name_unique');
        });
    }

    public function down(): void
    {
        $duplicate = DB::table('template_types')
            ->select('template_name')
            ->whereNotNull('template_name')
            ->groupBy('template_name')
            ->havingRaw('COUNT(*) > 1')
            ->value('template_name');

        if ($duplicate !== null) {
            // Rollback is intentionally refused when the newer schema has accepted duplicates.
            // Keep the migration applied and preserve rows rather than deleting valid user data.
            throw new RuntimeException(sprintf(
                'Cannot restore template_types.template_name uniqueness while duplicate value %s exists.',
                json_encode($duplicate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ));
        }

        Schema::table('template_types', function ($table) {
            $table->unique('template_name');
        });
    }
};
