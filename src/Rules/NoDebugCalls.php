<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets\Rules;

use OffsetSmoker\PestRatchets\Offense;
use OffsetSmoker\PestRatchets\Rule;
use OffsetSmoker\PestRatchets\SourceFile;
use OffsetSmoker\PestRatchets\Support\Calls;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;

/**
 * Flags debugging calls left in code: the dd(), dump() and ray() functions,
 * and the ->dd(), ->dump(), ->ddRawSql(), ->dumpRawSql() and ->ray()
 * methods Laravel collections, query builders and Ray add.
 */
final readonly class NoDebugCalls implements Rule
{
    /**
     * @param  list<string>  $functions  global function names to flag
     * @param  list<string>  $methods  method names to flag on any object; pass [] to flag functions only
     */
    public function __construct(
        private array $functions = ['dd', 'dump', 'ray'],
        private array $methods = ['dd', 'dump', 'ddRawSql', 'dumpRawSql', 'ray'],
    ) {}

    public function check(SourceFile $file): array
    {
        $offenses = [];

        foreach ($file->findInstanceOf(FuncCall::class) as $call) {
            $name = Calls::globalFunction($call);

            if (Calls::nameIn($name, $this->functions)) {
                $offenses[] = new Offense($call->getStartLine(), "debug call {$name}() left in code");
            }
        }

        foreach ([...$file->findInstanceOf(MethodCall::class), ...$file->findInstanceOf(NullsafeMethodCall::class)] as $call) {
            $name = Calls::methodName($call);

            if ($name !== null && Calls::nameIn($name, $this->methods)) {
                $offenses[] = new Offense($call->getStartLine(), "debug call ->{$name}() left in code");
            }
        }

        return $offenses;
    }
}
