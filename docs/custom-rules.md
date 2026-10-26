# Writing a custom rule

A rule is a class that implements `OffsetSmoker\PestRatchets\Rule`:

```php
interface Rule
{
    /** @return list<Offense> */
    public function check(SourceFile $file): array;
}
```

The ratchet calls `check()` once for every `.php` file it scans. The
`SourceFile` holds:

- `relativePath`: the path from the ratchet root, with forward slashes
  (`app/Http/Kernel.php`). Baselines record this path.
- `absolutePath`: where the file is on disk.
- `ast`: the file's statements, parsed by
  [nikic/php-parser](https://github.com/nikic/PHP-Parser) 5 and run through
  its `NameResolver`, so class names are fully qualified and `use`
  imports are already applied.
- `isUnder(string ...$paths)`: whether the file is in one of those
  directories (or is one of those files), relative to the root.
- `findInstanceOf(class-string $type)`: every node of that type in the
  file, in source order.

Comments and string contents are not nodes. A rule that looks for calls
does not see `// dd($x)` or `'env("X")'`, and you never have to filter
them out.

## The message is the baseline key

Each `Offense` has a line and a message. The baseline stores the file, the
message and how many times that message occurs in the file. It does not
store lines, so code moving within a file never disturbs it.

That makes the message part of the rule's contract:

- Keep it stable. No line numbers, timestamps or counters in it.
- Make it specific enough to tell offenses apart when that matters. Putting
  the class or function name in it (as `ModelsDeclareFillable` does) means
  fixing one model and breaking another shows up as one stale entry and
  one new offender, not as no change at all.
- Keep it to one line. `Offense` rejects empty and multi-line messages.

If you change a message, existing baselines stop matching: every entry
becomes stale and every offender new. Regenerate the baseline in the same
commit with `vendor/bin/pest --update-ratchets`.

## Example: no facades in the domain layer

This rule flags static calls on Laravel facades in `app/Domain`, where
dependencies should come in through the constructor.

```php
<?php

declare(strict_types=1);

namespace Tests\Ratchets;

use OffsetSmoker\PestRatchets\Offense;
use OffsetSmoker\PestRatchets\Rule;
use OffsetSmoker\PestRatchets\SourceFile;
use OffsetSmoker\PestRatchets\Support\Calls;
use PhpParser\Node\Expr\StaticCall;

final readonly class NoFacadesInDomain implements Rule
{
    public function check(SourceFile $file): array
    {
        if (! $file->isUnder('app/Domain')) {
            return [];
        }

        $offenses = [];

        foreach ($file->findInstanceOf(StaticCall::class) as $call) {
            $class = Calls::staticClass($call);

            if ($class !== null && str_starts_with($class, 'Illuminate\Support\Facades\\')) {
                $offenses[] = new Offense($call->getStartLine(), "facade {$class} used in the domain layer; inject the dependency instead");
            }
        }

        return $offenses;
    }
}
```

Use it like the bundled rules:

```php
use Tests\Ratchets\NoFacadesInDomain;

ratchet(NoFacadesInDomain::class)->in('app')->baseline('tests/ratchets/domain-facades.json');
```

Pass an instance instead of a class name when the rule takes constructor
arguments: `ratchet(new MyRule(['app/Legacy']))`.

## Helpers for matching calls

`OffsetSmoker\PestRatchets\Support\Calls` has the small pieces the bundled
rules share:

| Method | Returns |
|---|---|
| `globalFunction(FuncCall)` | the lowercased global function a call resolves to (`dd`, `env`), or null for `$fn()`, `Foo\dd()` and functions brought in with `use function` |
| `methodName(MethodCall\|NullsafeMethodCall\|StaticCall)` | the method name as written, or null when it is dynamic |
| `staticClass(StaticCall)` | the fully qualified class of `Foo::bar()`, or null for `static::`, `self::`, `parent::` and `$class::` |
| `isStaticCallOn(StaticCall, list<string>)` | whether the call is on one of those classes, compared without case |
| `argument(call, position, names)` | the expression passed for a parameter by position or by name, or null when absent, unpacked or a first-class callable |
| `nameIn(?string, list<string>)` | a case-insensitive `in_array()` that accepts null |

## Testing a rule

Write fixture files that contain the offending code, the same code
commented out and inside strings, and the near misses (a method with the
same name, a namespaced function), then assert on what the ratchet finds:

```php
use OffsetSmoker\PestRatchets\Ratchet;

it('flags facades in the domain layer only', function () {
    $offenders = Ratchet::rule(NoFacadesInDomain::class)
        ->root(__DIR__.'/fixtures/domain')
        ->in('app')
        ->offenders();

    expect(array_map(fn ($o) => "{$o->file}:{$o->line}", $offenders))
        ->toBe(['app/Domain/Billing.php:12']);
});
```

## What a rule cannot do

A rule sees one file at a time and has no type information: it reads
syntax, not runtime types. `$query->whereRaw()` is matched by its method
name whatever `$query` is, and a class counts as a model only when it
names a known base class in its own `extends`. If a rule needs to follow
inheritance across files or know the type of an expression, it is a job
for a PHPStan rule rather than a ratchet.
