<?php

namespace App\Services\QrCodes\Payloads;

abstract class PayloadType
{
    abstract public function key(): string;

    abstract public function descriptor(): array;

    abstract public function rules(): array;

    abstract public function encode(array $payload): string;

    public function redirects(string $content): bool
    {
        return false;
    }
}
