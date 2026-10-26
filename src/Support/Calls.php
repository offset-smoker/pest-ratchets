<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets\Support;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;

/**
 * Helpers for matching calls in a name-resolved AST, for use in rules.
 */
final class Calls
{
    /**
     * The lowercased name of the global function a call resolves to, or null
     * when the call is dynamic (`$fn()`) or names a namespaced function
     * (`Foo\dd()`, or `dd()` after `use function Foo\dd`).
     *
     * An unqualified call inside a namespace counts as global: PHP falls back
     * to the global function unless the namespace defines one of that name.
     */
    public static function globalFunction(FuncCall $call): ?string
    {
        $name = $call->name;

        if (! $name instanceof Name) {
            return null;
        }

        if ($name instanceof FullyQualified && count($name->getParts()) !== 1) {
            return null;
        }

        if (! $name instanceof FullyQualified && ! $name->isUnqualified()) {
            return null;
        }

        return $name->toLowerString();
    }

    /**
     * The method name as written, or null when it is dynamic (`$obj->$name()`).
     */
    public static function methodName(MethodCall|NullsafeMethodCall|StaticCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : null;
    }

    /**
     * The class a static call names, fully qualified without a leading
     * backslash, or null for `static::`, `$class::` and the like.
     */
    public static function staticClass(StaticCall $call): ?string
    {
        if (! $call->class instanceof Name || $call->class->isSpecialClassName()) {
            return null;
        }

        return $call->class->toString();
    }

    /**
     * Whether a static call is to one of the given classes, compared without
     * case as PHP does.
     *
     * @param  list<string>  $classes
     */
    public static function isStaticCallOn(StaticCall $call, array $classes): bool
    {
        $class = self::staticClass($call);

        if ($class === null) {
            return false;
        }

        foreach ($classes as $candidate) {
            if (strcasecmp(ltrim($candidate, '\\'), $class) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The argument passed for a parameter, by name or by position. Null when
     * it is absent, unpacked (`...$args`) or the call is a first-class
     * callable (`whereRaw(...)`).
     *
     * @param  list<string>  $names  the parameter's possible names
     */
    public static function argument(FuncCall|MethodCall|NullsafeMethodCall|StaticCall $call, int $position, array $names): ?Expr
    {
        if ($call->isFirstClassCallable()) {
            return null;
        }

        $positional = 0;

        foreach ($call->getArgs() as $arg) {
            if ($arg->unpack) {
                return null;
            }

            if ($arg->name !== null) {
                if (in_array($arg->name->toString(), $names, true)) {
                    return $arg->value;
                }

                continue;
            }

            if ($positional === $position) {
                return $arg->value;
            }

            $positional++;
        }

        return null;
    }

    /**
     * @param  list<string>  $names
     */
    public static function nameIn(?string $name, array $names): bool
    {
        if ($name === null) {
            return false;
        }

        foreach ($names as $candidate) {
            if (strcasecmp($candidate, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
