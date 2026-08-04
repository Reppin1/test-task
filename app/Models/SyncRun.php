<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $wallet_id null = batch-прогон по всем активным кошелькам
 * @property SyncTrigger $trigger
 * @property SyncStatus $status
 * @property int $fetched_count
 * @property int $created_count
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read Wallet|null $wallet
 */
class SyncRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'trigger',
        'status',
        'fetched_count',
        'created_count',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => SyncTrigger::class,
            'status' => SyncStatus::class,
            'fetched_count' => 'integer',
            'created_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isBatch(): bool
    {
        return $this->wallet_id === null;
    }

    public function durationInSeconds(): ?float
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return round((float) ($this->finished_at->getPreciseTimestamp(3) - $this->started_at->getPreciseTimestamp(3)) / 1000, 3);
    }
}
