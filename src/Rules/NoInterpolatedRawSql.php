<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets\Rules;

use OffsetSmoker\PestRatchets\Offense;
use OffsetSmoker\PestRatchets\Rule;
use OffsetSmoker\PestRatchets\SourceFile;
use OffsetSmoker\PestRatchets\Support\Calls;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\String_;

/**
 * Flags raw SQL built from variables: a DB::raw(), DB::select() (and the
 * other raw DB facade calls) or ->whereRaw()-style call whose SQL argument
 * is an interpolated string ("... {$id}"), a concatenation with anything
 * but literals and constants, or a sprintf() of non-literal values. Values
 * belong in ? bindings.
 *
 * A bare variable passed as the SQL ($sql) is not flagged: where it was
 * built is beyond what one expression shows.
 */
final readonly class NoInterpolatedRawSql implements Rule
{
    /** Static calls on the DB facade whose first argument is SQL. */
    private const array FACADE_METHODS = ['raw', 'select', 'selectOne', 'scalar', 'cursor', 'statement', 'unprepared', 'insert', 'update', 'delete', 'affectingStatement'];

    /** Query and Eloquent builder methods whose first argument is SQL, called on an instance or statically on a model. */
    private const array RAW_METHODS = ['whereRaw', 'orWhereRaw', 'havingRaw', 'orHavingRaw', 'selectRaw', 'orderByRaw', 'groupByRaw', 'fromRaw', 'updateFrom'];

    /** The names Laravel gives the SQL parameter of those methods. */
    private const array SQL_PARAMETERS = ['sql', 'expression', 'query', 'value'];

    /**
     * @param  list<string>  $facades  classes treated as the DB facade
     */
    public function __construct(
        private array $facades = ['Illuminate\Support\Facades\DB', 'DB'],
    ) {}

    public function check(SourceFile $file): array
    {
        $offenses = [];

        foreach ($file->findInstanceOf(StaticCall::class) as $call) {
            $method = Calls::methodName($call);

            $isFacadeSql = Calls::isStaticCallOn($call, $this->facades) && Calls::nameIn($method, self::FACADE_METHODS);

            if (($isFacadeSql || Calls::nameIn($method, self::RAW_METHODS)) && $this->sqlIsInterpolated($call)) {
                $label = $isFacadeSql ? "DB::{$method}()" : "{$method}()";
                $offenses[] = $this->offense($call, $label);
            }
        }

        foreach ([...$file->findInstanceOf(MethodCall::class), ...$file->findInstanceOf(NullsafeMethodCall::class)] as $call) {
            $method = Calls::methodName($call);

            if (Calls::nameIn($method, self::RAW_METHODS) && $this->sqlIsInterpolated($call)) {
                $offenses[] = $this->offense($call, "->{$method}()");
            }
        }

        return $offenses;
    }

    private function offense(Expr $call, string $label): Offense
    {
        return new Offense($call->getStartLine(), "{$label} SQL built from interpolated or concatenated values; pass them as ? bindings");
    }

    private function sqlIsInterpolated(MethodCall|NullsafeMethodCall|StaticCall $call): bool
    {
        $sql = Calls::argument($call, 0, self::SQL_PARAMETERS);

        return $sql !== null && $this->isInterpolated($sql);
    }

    private function isInterpolated(Expr $expr): bool
    {
        return match (true) {
            $expr instanceof InterpolatedString => ! $this->isConstant($expr),
            $expr instanceof Concat => ! $this->isConstant($expr),
            $expr instanceof Ternary => ($expr->if !== null && $this->isInterpolated($expr->if)) || $this->isInterpolated($expr->else),
            $expr instanceof FuncCall => Calls::nameIn(Calls::globalFunction($expr), ['sprintf', 'vsprintf']) && ! $this->isConstant($expr),
            default => false,
        };
    }

    /**
     * Whether an expression is made only of literals and constants.
     */
    private function isConstant(Expr $expr): bool
    {
        if ($expr instanceof InterpolatedString) {
            foreach ($expr->parts as $part) {
                if (! $part instanceof InterpolatedStringPart) {
                    return false;
                }
            }

            return true;
        }

        if ($expr instanceof Concat) {
            return $this->isConstant($expr->left) && $this->isConstant($expr->right);
        }

        if ($expr instanceof Ternary) {
            return ($expr->if === null || $this->isConstant($expr->if)) && $this->isConstant($expr->else);
        }

        if ($expr instanceof FuncCall && Calls::nameIn(Calls::globalFunction($expr), ['sprintf', 'vsprintf'])) {
            if ($expr->isFirstClassCallable()) {
                return true;
            }

            foreach ($expr->getArgs() as $arg) {
                if ($arg->unpack || ! $this->isConstant($arg->value)) {
                    return false;
                }
            }

            return true;
        }

        return $expr instanceof String_
            || $expr instanceof Int_
            || $expr instanceof Float_
            || $expr instanceof MagicConst
            || $expr instanceof ConstFetch
            || $expr instanceof ClassConstFetch;
    }
}
