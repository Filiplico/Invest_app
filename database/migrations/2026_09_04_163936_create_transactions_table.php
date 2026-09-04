<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('instrument')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('price_per_unit', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['client_id', 'instrument']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
