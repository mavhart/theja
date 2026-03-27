<?php

namespace App\Services\CashRegister\Providers;

interface RtProviderInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendScontrino(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendChiusura(array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array;
}

