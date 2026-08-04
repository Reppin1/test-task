<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepositJsonResource;
use App\Models\Deposit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepositController extends Controller
{
    /**
     * GET /api/deposits?wallet=T...&status=confirmed&client=<uuid>&from=&until=&per_page=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'wallet' => ['sometimes', 'string', 'max:64'],
            'client' => ['sometimes', 'string', 'max:36'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,ignored'],
            'from' => ['sometimes', 'date'],
            'until' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $deposits = Deposit::query()
            ->with(['wallet.client'])
            ->when(
                $validated['wallet'] ?? null,
                fn (Builder $query, string $wallet): Builder => $query->whereHas(
                    'wallet',
                    fn (Builder $q): Builder => ctype_digit($wallet)
                        ? $q->whereKey((int) $wallet)
                        : $q->where('address', $wallet),
                ),
            )
            ->when(
                $validated['client'] ?? null,
                fn (Builder $query, string $client): Builder => $query->whereHas(
                    'wallet.client',
                    fn (Builder $q): Builder => ctype_digit($client)
                        ? $q->whereKey((int) $client)
                        : $q->where('uuid', $client),
                ),
            )
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('status', DepositStatus::from($status)),
            )
            ->when(
                $validated['from'] ?? null,
                fn (Builder $query, string $from): Builder => $query->where('block_timestamp', '>=', $from),
            )
            ->when(
                $validated['until'] ?? null,
                fn (Builder $query, string $until): Builder => $query->where('block_timestamp', '<=', $until),
            )
            ->orderByDesc('block_timestamp')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        return DepositJsonResource::collection($deposits);
    }

    /**
     * GET /api/deposits/{tx_hash}
     */
    public function show(string $txHash): DepositJsonResource
    {
        $deposit = Deposit::query()
            ->with(['wallet.client'])
            ->where('tx_hash', $txHash)
            ->firstOrFail();

        return new DepositJsonResource($deposit);
    }
}
