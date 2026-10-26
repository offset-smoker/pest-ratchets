<?php

namespace App;

use function App\Support\env;

final class Lookalikes
{
    public function notTheGlobalHelper(object $settings): string
    {
        $fn = 'env';

        return env('IMPORTED_FUNCTION')
            . Support\env('QUALIFIED_FUNCTION')
            . $settings->env('A_METHOD')
            . Config::env('A_STATIC_METHOD')
            . $fn('A_DYNAMIC_CALL');
    }
}
