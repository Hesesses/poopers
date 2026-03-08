<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('league_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('type');
            $table->integer('current_count')->default(0);
            $table->integer('best_count')->default(0);
            $table->date('started_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'league_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaks');
    }
};
