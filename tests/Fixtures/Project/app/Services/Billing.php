<?php

namespace App\Services;

final class Billing
{
    public function currency(): string
    {
        // Was: return env('BILLING_CURRENCY');
        /* return env('BILLING_CURRENCY', 'usd'); */
        return env('BILLING_CURRENCY', 'usd');
    }
}
