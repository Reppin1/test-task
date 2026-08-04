<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $tx_hash
 * @property string $from_address
 * @property string $to_address
 * @property string $amount decimal(36,6) — всегда строка, никогда float
 * @property string $token_symbol
 * @property string|null $contract_address
 * @property Carbon|null $block_timestamp
 * @property DepositStatus $status
 * @property array|null $raw_payload
 * @property-read Wallet $wallet
 */
class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'tx_hash',
        'from_address',
        'to_address',
        'amount',
        'token_symbol',
        'contract_address',
        'block_timestamp',
        'status',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            // decimal:6 отдаёт строку — деньги во float не превращаем.
            'amount' => 'decimal:6',
            'block_timestamp' => 'datetime',
            'status' => DepositStatus::class,
            'raw_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function explorerUrl(): string
    {
        return config('tronscan.explorer_tx_url').$this->tx_hash;
    }
}
