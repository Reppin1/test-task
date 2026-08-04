<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deposit
 */
class DepositJsonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tx_hash' => $this->tx_hash,
            // Строка, а не число: 6 знаков после точки без потерь.
            'amount' => (string) $this->amount,
            'token' => $this->token_symbol,
            'status' => $this->status->value,
            'from_address' => $this->from_address,
            'to_address' => $this->to_address,
            'block_timestamp' => $this->block_timestamp?->toIso8601String(),
            'explorer_url' => $this->explorerUrl(),
            'wallet' => [
                'id' => $this->wallet->id,
                'address' => $this->wallet->address,
                'label' => $this->wallet->label,
                'is_active' => $this->wallet->is_active,
            ],
            'client' => [
                'uuid' => $this->wallet->client->uuid,
                'name' => $this->wallet->client->name,
                'status' => $this->wallet->client->status->value,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
