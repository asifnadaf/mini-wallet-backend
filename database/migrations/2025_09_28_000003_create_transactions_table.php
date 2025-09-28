<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->constrained('users');
            $table->decimal('amount', 15, 2);
            $table->decimal('commission_fee', 15, 2);
            $table->timestamp('completed_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['sender_id', 'completed_at']);
            $table->index(['receiver_id', 'completed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
