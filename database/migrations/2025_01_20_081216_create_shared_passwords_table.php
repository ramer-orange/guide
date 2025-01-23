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
        Schema::create('shared_passwords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_id')->constrained('travel_overviews')->cascadeOnDelete();
            $table->string('shared_password')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_passwords');
    }
};
