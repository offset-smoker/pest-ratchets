<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets\Rules;

use OffsetSmoker\PestRatchets\Offense;
use OffsetSmoker\PestRatchets\Rule;
use OffsetSmoker\PestRatchets\SourceFile;
use OffsetSmoker\PestRatchets\Support\Calls;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;

/**
 * Flags env() and Illuminate\Support\Env::get() outside the config
 * directory. Once `php artisan config:cache` has run, Laravel stops loading
 * .env, and those calls quietly return their default (often null) in
 * production. Application code should read config() instead.
 */
final readonly class NoDirectEnvCalls implements Rule
{
    /**
     * @param  list<string>  $allowedPaths  directories or files, relative to the ratchet root, where env() belongs
     */
    public function __construct(private array $allowedPaths = ['config']) {}

    public function check(SourceFile $file): array
    {
        if ($file->isUnder(...$this->allowedPaths)) {
            return [];
        }

        $where = implode(', ', array_map(static fn (string $path): string => rtrim($path, '/').'/', $this->allowedPaths));
        $offenses = [];

        foreach ($file->findInstanceOf(FuncCall::class) as $call) {
            if (Calls::globalFunction($call) === 'env') {
                $offenses[] = new Offense($call->getStartLine(), "env() called outside {$where}; read the value through config() instead");
            }
        }

        foreach ($file->findInstanceOf(StaticCall::class) as $call) {
            if (Calls::isStaticCallOn($call, ['Illuminate\Support\Env']) && Calls::nameIn(Calls::methodName($call), ['get', 'getOrFail'])) {
                $offenses[] = new Offense($call->getStartLine(), "Env::get() called outside {$where}; read the value through config() instead");
            }
        }

        return $offenses;
    }
}
