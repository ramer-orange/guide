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
        Schema::create('additional_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('travel_id')->constrained('travel_overviews')->cascadeOnDelete();
            $table->string('additionalComment_title')->nullable();
            $table->text('additionalComment_text')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_comments');
    }
};
