<?php

use App\Models\TravelOverview;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('packing_items', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('travel_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        DB::table('packing_items')
            ->select(['id', 'travel_id'])
            ->orderBy('id')
            ->chunkById(100, function ($packingItems) {
                foreach ($packingItems as $packingItem) {
                    $ownerId = TravelOverview::whereKey($packingItem->travel_id)->value('user_id');

                    if ($ownerId) {
                        DB::table('packing_items')
                            ->where('id', $packingItem->id)
                            ->update(['user_id' => $ownerId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
