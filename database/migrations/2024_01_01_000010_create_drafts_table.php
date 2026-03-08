<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained();
            $table->tinyInteger('type');
            $table->string('name')->nullable();
            $table->date('date')->index();
            $table->json('available_items');
            $table->json('pick_order');
            $table->integer('current_pick_index')->default(0);
            $table->tinyInteger('status');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
