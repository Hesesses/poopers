<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_noon_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date')->index();
            $table->integer('steps');
            $table->integer('modified_steps');
            $table->integer('position');
            $table->timestamps();

            $table->unique(['league_id', 'user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_noon_snapshots');
    }
};
