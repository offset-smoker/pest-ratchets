<?php

namespace App\Http\Controllers;

final class HomeController
{
    public function __invoke(): array
    {
        return [
            'name' => env('APP_NAME'),
            'debug' => env('APP_DEBUG'),
        ];
    }
}
