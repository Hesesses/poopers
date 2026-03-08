<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->default('💩');
            $table->string('timezone')->default('UTC');
            $table->string('invite_code', 10)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_pro_league')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
