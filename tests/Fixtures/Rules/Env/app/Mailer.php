<?php

namespace App;

use Illuminate\Support\Env;

final class Mailer
{
    public function key(): string
    {
        return env('MAIL_KEY');
    }

    public function fallback(): string
    {
        return \env('MAIL_FALLBACK', 'none') . Env::get('MAIL_REGION');
    }

    public function commentedOut(): string
    {
        // return env('MAIL_KEY');
        # env('HASH_COMMENT');
        /* env('BLOCK_COMMENT'); */
        /**
         * Example: env('DOCBLOCK')
         */
        $text = 'env("IN_A_STRING")';
        $doc = <<<'TXT'
        env('IN_A_NOWDOC')
        TXT;

        return $text . $doc . "env('IN_DOUBLE_QUOTES')";
    }

    public function required(): string
    {
        return Env::getOrFail('MAIL_SECRET');
    }
}
