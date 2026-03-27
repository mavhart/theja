<?php

namespace App\Services\CashRegister;

use App\Models\PointOfSale;
use App\Services\CashRegister\Providers\LogRtProvider;
use App\Services\CashRegister\Providers\RtProviderInterface;

class RtProviderFactory
{
    public function make(PointOfSale $pos): RtProviderInterface
    {
        $provider = strtolower((string) ($pos->rt_provider ?: env('RT_PROVIDER', 'log')));

        return match ($provider) {
            'log', 'default', '' => new LogRtProvider(),
            default => new LogRtProvider(),
        };
    }
}

