<?php

declare(strict_types=1);

namespace App\Domain\Deposits;

/**
 * Итог одного прогона синхронизации.
 *
 * fetched — сколько трансферов вернул API;
 * created — сколько новых депозитов записали;
 * ignored — созданы, но со статусом ignored (amount <= 0);
 * skipped — отфильтрованы (исходящие/чужой контракт) либо уже существуют.
 */
final readonly class SyncResult
{
    public function __construct(
        public int $fetched = 0,
        public int $created = 0,
        public int $ignored = 0,
        public int $skipped = 0,
        public ?int $runId = null,
        public ?string $error = null,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            fetched: $this->fetched + $other->fetched,
            created: $this->created + $other->created,
            ignored: $this->ignored + $other->ignored,
            skipped: $this->skipped + $other->skipped,
            runId: $this->runId,
            error: $this->error ?? $other->error,
        );
    }

    public function withRunId(?int $runId): self
    {
        return new self($this->fetched, $this->created, $this->ignored, $this->skipped, $runId, $this->error);
    }

    public function withError(?string $error): self
    {
        return new self($this->fetched, $this->created, $this->ignored, $this->skipped, $this->runId, $error);
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'fetched' => $this->fetched,
            'created' => $this->created,
            'ignored' => $this->ignored,
            'skipped' => $this->skipped,
            'run_id' => $this->runId,
            'error' => $this->error,
        ];
    }
}
