<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('anomaly_type');
            $table->json('details')->nullable();
            $table->string('severity');
            $table->boolean('reviewed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index('anomaly_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_anomalies');
    }
};
