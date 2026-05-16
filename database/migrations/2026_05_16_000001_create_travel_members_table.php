<?php

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
        Schema::create('travel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('travel_id')->constrained('travel_overviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['travel_id', 'user_id']);
        });

        DB::table('travel_overviews')
            ->select(['id', 'user_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(100, function ($overviews) {
                foreach ($overviews as $overview) {
                    DB::table('travel_members')->insertOrIgnore([
                        'travel_id' => $overview->id,
                        'user_id' => $overview->user_id,
                        'role' => 'owner',
                        'created_at' => $overview->created_at,
                        'updated_at' => $overview->updated_at,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_members');
    }
};
