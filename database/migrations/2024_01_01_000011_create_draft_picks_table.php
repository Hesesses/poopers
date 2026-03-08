<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('item_id')->constrained();
            $table->integer('pick_number');
            $table->timestamp('picked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_picks');
    }
};
