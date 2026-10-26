# offset-smoker/pest-ratchets

Architecture ratchets for [Pest](https://pestphp.com). A rule finds the
code that breaks a convention: `env()` outside `config/`, a `dd()` left in,
raw SQL built from variables, a model without `$fillable`. Most codebases
already break their conventions somewhere, so a plain architecture test
either fails on day one or gets switched off. A ratchet records today's
offenders in a committed JSON baseline and holds the line from there: it
fails on any new offender, and it also fails when a baseline entry no
longer offends, so paid-off debt has to come off the list. The baseline
can only shrink. Rules read the code with a real PHP parser
([nikic/php-parser](https://github.com/nikic/PHP-Parser)), not regular
expressions, so commented-out code and the contents of strings never count.

## Requirements

- PHP 8.3 or later
- Pest 4.5 or later (Pest 5 needs PHP 8.4)

CI runs PHP 8.3 and 8.4, each with the newest and the lowest dependency
versions `composer.json` allows.

## Installation

```bash
composer require --dev offset-smoker/pest-ratchets
```

Pest discovers the package as a plugin through `pestphp/pest-plugin`,
which every Pest project already allows in `config.allow-plugins`. That is
what adds the `--update-ratchets` and `--prune-ratchets` options.

## Usage

Create a test file, for example `tests/Arch/RatchetsTest.php`:

```php
<?php

use OffsetSmoker\PestRatchets\Rules\ModelsDeclareFillable;
use OffsetSmoker\PestRatchets\Rules\NoDebugCalls;
use OffsetSmoker\PestRatchets\Rules\NoDirectEnvCalls;
use OffsetSmoker\PestRatchets\Rules\NoInterpolatedRawSql;

ratchet(NoDirectEnvCalls::class)->in('app', 'routes')->baseline('tests/ratchets/env.json');
ratchet(NoDebugCalls::class)->in('app', 'routes', 'database')->baseline('tests/ratchets/debug.json');
ratchet(NoInterpolatedRawSql::class)->in('app')->baseline('tests/ratchets/raw-sql.json');
ratchet(ModelsDeclareFillable::class)->in('app/Models')->baseline('tests/ratchets/fillable.json');
```

`ratchet()` works like Pest's `arch()`: each call defines one test, named
after the rule (`ratchet NoDirectEnvCalls`). Paths are relative to the
project root.

Then record today's offenders, once, and commit the baselines:

```bash
vendor/bin/pest --update-ratchets
git add tests/ratchets
```

From then on `vendor/bin/pest` runs the ratchets with everything else.

### When a ratchet fails

A new offender fails with its file and line:

```
Ratchet NoDirectEnvCalls failed (baseline tests/ratchets/env.json).

New offenders. Fix them; the baseline may only shrink:
  app/Services/Billing.php:14  env() called outside config/; read the value through config() instead
```

If the file already had baselined offenders of that kind, every one of
them is listed with a note such as `(3 found in this file, the baseline
allows 2; it cannot tell which of these are new)`, because the baseline
counts per file instead of storing lines.

Fixing an offender, or deleting the file, fails the other way:

```
Stale baseline entries. This debt is paid; remove it from the baseline:
  app/Legacy/Old.php  env() called outside config/; read the value through config() instead
    (the baseline allows 1, 0 remain)

Run vendor/bin/pest --prune-ratchets to remove stale entries without adding any.
```

### Updating a baseline

| Command | What it does |
|---|---|
| `vendor/bin/pest --prune-ratchets` | Lowers counts and removes entries that no longer offend. Never adds an entry or raises a count, so new offenders still fail. Safe to run any time. |
| `vendor/bin/pest --update-ratchets` | Rewrites each baseline to every current offender, then passes. This is how a baseline grows, so use it deliberately and review the diff. |

Both work with `--filter` and `--parallel`. Where you cannot add options
to the Pest command, set `PEST_RATCHETS=prune` or `PEST_RATCHETS=update`
instead. The baseline is only rewritten when its content changes.

The baseline is plain, sorted JSON, so a change to it reads clearly in
review:

```json
{
    "rule": "OffsetSmoker\\PestRatchets\\Rules\\NoDirectEnvCalls",
    "offenders": [
        {
            "file": "app/Legacy/Old.php",
            "message": "env() called outside config/; read the value through config() instead",
            "count": 1
        }
    ]
}
```

### Inside a test, or without Pest's runner

`Ratchet` is the same thing without the test definition:

```php
use OffsetSmoker\PestRatchets\Ratchet;
use OffsetSmoker\PestRatchets\Rules\NoDebugCalls;

it('keeps debug calls out of the app', function () {
    Ratchet::rule(NoDebugCalls::class)
        ->in('app')
        ->baseline('tests/ratchets/debug.json')
        ->assert();
});
```

`check()` returns a `RatchetResult` (`passed()`, `newOffenders`,
`staleEntries`, `report()`) without asserting, and `offenders()` returns
what the rule found.

## Rules

| Rule | Flags | Options |
|---|---|---|
| `NoDirectEnvCalls` | `env()` and `Illuminate\Support\Env::get()` outside `config/`. After `config:cache`, Laravel no longer loads `.env`, and these calls return their default. | `new NoDirectEnvCalls(allowedPaths: ['config', 'bootstrap/app.php'])` |
| `NoDebugCalls` | The `dd()`, `dump()` and `ray()` functions, and the `->dd()`, `->dump()`, `->ddRawSql()`, `->dumpRawSql()` and `->ray()` methods on any object. | `new NoDebugCalls(functions: [...], methods: [...])`; `methods: []` checks functions only |
| `NoInterpolatedRawSql` | Raw SQL whose SQL argument is an interpolated string (`"id = $id"`), a concatenation with anything other than literals and constants, or a `sprintf()` of non-literal values. Checks `DB::raw()`, `DB::select()`, `DB::selectOne()`, `DB::scalar()`, `DB::cursor()`, `DB::statement()`, `DB::unprepared()`, `DB::insert()`, `DB::update()`, `DB::delete()` and `DB::affectingStatement()`, and `whereRaw()`, `orWhereRaw()`, `havingRaw()`, `orHavingRaw()`, `selectRaw()`, `orderByRaw()`, `groupByRaw()`, `fromRaw()` and `updateFrom()` called on a builder or statically on a model. | `new NoInterpolatedRawSql(facades: ['Illuminate\Support\Facades\DB', 'DB', 'App\Support\Db'])` |
| `ModelsDeclareFillable` | Concrete models with no `$fillable` property, and any model, abstract ones included, that sets `$guarded = []`. | `new ModelsDeclareFillable(modelClasses: [...])`: the base classes whose direct subclasses count as models. The default is Eloquent's `Model`, `Pivot`, `MorphPivot` and `Illuminate\Foundation\Auth\User`; add your own base model. |

Pass an instance to `ratchet()` to use options:
`ratchet(new NoDebugCalls(methods: []))->in('app')`.

To write your own rule, see [docs/custom-rules.md](docs/custom-rules.md).

## Configuration

Everything is set on the ratchet itself:

| Method | Purpose |
|---|---|
| `in(string ...$paths)` | Directories (scanned recursively for `.php` files) or single files. Required. |
| `exclude(string ...$paths)` | Directories or files to skip. |
| `baseline(string $path)` | The baseline JSON file. Without one, any offender fails. |
| `root(string $path)` | The directory paths resolve against and baselines record paths from. `ratchet()` uses the project root; `Ratchet::rule()` uses the working directory. |
| `group(string ...$groups)` | (`ratchet()` only) Pest groups for the test, so `--group=ratchets` runs just these. |

Misconfiguration is an error, not a pass. A path that does not exist, a
path with no `.php` files in it, a path outside the root, a file PHP
cannot parse, paths whose every file is excluded, a missing baseline file
and a malformed baseline each stop the test with a message saying what is
wrong. A ratchet that silently scanned nothing would be worse than none.

## Testing

```bash
composer install
composer test      # Pest
composer analyse   # PHPStan, level max, strict rules, no baseline
composer lint      # Pint
```

The suite runs the rules over fixture projects in `tests/Fixtures`. The
tests prove both failure directions, new offenders and stale entries,
that commented-out code and strings are never counted, and that code
moving within a file leaves the baseline alone. `tests/Feature/EndToEndTest.php`
also runs Pest itself in a separate process on a copy of a fixture project,
to show that `ratchet()` fails a real run and that `--update-ratchets`,
`--prune-ratchets` and `PEST_RATCHETS` reach the tests, in parallel runs too.

## Status and limitations

Version 0.1.0. The four rules and the baseline format are tested, but the
package has not yet been used on a large outside codebase, so expect
false positives and gaps in the rules, and possibly changes to their
messages (which would mean regenerating baselines).

- **Syntax, not types.** Rules read one file at a time with no type
  information. `->whereRaw()` and `->dump()` are matched by method name,
  whatever the object. `ModelsDeclareFillable` only knows a class is a
  model when its own `extends` names a configured base class; it cannot
  follow a chain of parents through other files.
- **What counts as interpolated SQL.** `NoInterpolatedRawSql` judges the
  SQL expression it can see. `whereRaw($sql)` with a variable built
  elsewhere is not flagged, and neither are SQL strings built by helpers
  other than `sprintf()` and `vsprintf()`.
- **Dynamic calls are not seen.** `$fn = 'env'; $fn('X')`,
  `call_user_func('dd', $x)` and the like pass every rule.
- **Unqualified calls inside a namespace** (`env()` in `namespace App;`)
  are treated as the global function, as PHP resolves them unless that
  namespace defines its own function of that name.
- **Property declarations only.** `ModelsDeclareFillable` looks for a
  `$fillable` property and a `$guarded = []` default. It does not see
  `$guarded` set in a constructor, or `Model::unguard()`.
- **Counts, not lines.** The baseline counts each message per file. If
  you fix one offender and add another of the same kind in the same file,
  the ratchet cannot tell, and it passes.
- **`in('.')` scans everything**, `vendor/` included. Name the
  directories you mean, or add `exclude('vendor')`.
- **Not tested on Windows.** Paths are normalized to forward slashes, but
  CI runs on Linux only.
- **Pest's test impact analysis** (Pest 5's `--tia`) replays tests whose
  covered source has not changed. Ratchets read files rather than execute
  them, so run them without `--tia`, or in a separate full run in CI.

## Security

See [SECURITY.md](SECURITY.md). The package reads PHP files and the
baseline JSON, and writes only baseline files, and only when asked to
with `--update-ratchets` or `--prune-ratchets`. Baseline entries are
validated: a file path must be relative, with no `..` segments.

## How this was built

Written with Claude Code under the direction and review of Brandon Wagner.

## License

MIT. See [LICENSE](LICENSE).
