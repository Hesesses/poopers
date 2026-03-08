<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_steps', function (Blueprint $table) {
            $table->json('hourly_steps')->nullable()->after('modified_steps');
        });
    }

    public function down(): void
    {
        Schema::table('daily_steps', function (Blueprint $table) {
            $table->dropColumn('hourly_steps');
        });
    }
};
