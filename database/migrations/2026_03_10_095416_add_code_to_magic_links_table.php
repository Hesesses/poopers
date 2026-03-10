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
        Schema::table('magic_links', function (Blueprint $table) {
            $table->string('code', 6)->nullable()->index()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('magic_links', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
