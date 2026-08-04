<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\TronScan\Support\TronAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Валидация TRON-адреса: формат «T + 33 base58» и (по умолчанию) checksum.
 *
 * Строгость управляется TRON_STRICT_ADDRESS_CHECKSUM либо явным аргументом.
 */
class TronAddressRule implements ValidationRule
{
    public function __construct(private readonly ?bool $strict = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! TronAddress::matchesFormat($value)) {
            $fail('The :attribute must be a TRON base58 address (starts with «T», 34 characters).');

            return;
        }

        if (! TronAddress::isValid($value, $this->strict)) {
            $fail('The :attribute has an invalid TRON checksum.');
        }
    }
}
