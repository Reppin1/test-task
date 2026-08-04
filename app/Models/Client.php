<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientStatus;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property ClientStatus $status
 * @property string|null $notes
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $client): void {
            $client->uuid ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<Wallet, $this> */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /** @return HasManyThrough<Deposit, Wallet, $this> */
    public function deposits(): HasManyThrough
    {
        return $this->hasManyThrough(Deposit::class, Wallet::class);
    }

    public function isBlocked(): bool
    {
        return $this->status === ClientStatus::Blocked;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
