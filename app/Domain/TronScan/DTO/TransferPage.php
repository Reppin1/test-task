<?php

declare(strict_types=1);

namespace App\Domain\TronScan\DTO;

use Countable;

/**
 * Страница трансферов + общее количество из ответа API.
 */
final readonly class TransferPage implements Countable
{
    /**
     * @param  list<Trc20Transfer>  $transfers
     */
    public function __construct(
        public array $transfers,
        public int $total,
        public int $start,
        public int $limit,
    ) {}

    public function count(): int
    {
        return count($this->transfers);
    }

    public function isEmpty(): bool
    {
        return $this->transfers === [];
    }

    /**
     * Есть ли ещё страницы (по total из ответа).
     */
    public function hasMore(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return ($this->start + count($this->transfers)) < $this->total;
    }
}
