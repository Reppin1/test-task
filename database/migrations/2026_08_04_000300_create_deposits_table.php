<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            // Ключ идемпотентности: повторный sync не создаёт дубль.
            $table->string('tx_hash', 128)->unique();
            $table->string('from_address', 64)->index();
            $table->string('to_address', 64)->index();
            // USDT TRC-20: 6 decimals. Никаких float — только decimal/string.
            $table->decimal('amount', 36, 6);
            $table->string('token_symbol', 32)->default('USDT');
            $table->string('contract_address', 64)->nullable();
            $table->timestamp('block_timestamp')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'ignored'])->default('pending')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            // Лента депозитов кошелька по дате блока.
            $table->index(['wallet_id', 'block_timestamp']);
            $table->index(['status', 'block_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
