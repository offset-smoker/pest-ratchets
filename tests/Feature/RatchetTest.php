<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Exceptions\RatchetException;
use OffsetSmoker\PestRatchets\Mode;
use OffsetSmoker\PestRatchets\Ratchet;
use OffsetSmoker\PestRatchets\Rules\NoDebugCalls;
use OffsetSmoker\PestRatchets\Rules\NoDirectEnvCalls;
use PHPUnit\Framework\AssertionFailedError;

const ENV_MESSAGE = 'env() called outside config/; read the value through config() instead';

function envRatchet(string $project): Ratchet
{
    return Ratchet::rule(NoDirectEnvCalls::class)
        ->root($project)
        ->in('app', 'config')
        ->baseline('tests/ratchets/env.json');
}

it('passes when the offenders match the baseline', function (): void {
    $result = envRatchet(temporaryProject())->assert();

    expect($result->passed())->toBeTrue()
        ->and($result->offenderCount)->toBe(4)
        ->and($result->report())->toBe('Ratchet NoDirectEnvCalls passed.');
});

it('fails on an offender in a file the baseline does not list, naming the file and line', function (): void {
    $project = temporaryProject();
    writeFile("{$project}/app/Services/Fresh.php", "<?php\n\nnamespace App\\Services;\n\nfunction fresh(): ?string\n{\n    return env('FRESH');\n}\n");

    $result = envRatchet($project)->check();

    expect($result->passed())->toBeFalse()
        ->and($result->staleEntries)->toBe([])
        ->and($result->report())->toBe(implode("\n", [
            'Ratchet NoDirectEnvCalls failed (baseline tests/ratchets/env.json).',
            '',
            'New offenders. Fix them; the baseline may only shrink:',
            '  app/Services/Fresh.php:7  '.ENV_MESSAGE,
        ]));

    expect(fn () => envRatchet($project)->assert())
        ->toThrow(AssertionFailedError::class, 'app/Services/Fresh.php:7');
});

it('fails on one more offender in a file the baseline already lists', function (): void {
    $project = temporaryProject();
    replaceInFile(
        "{$project}/app/Http/Controllers/HomeController.php",
        "'debug' => env('APP_DEBUG'),",
        "'debug' => env('APP_DEBUG'),\n            'url' => env('APP_URL'),",
    );

    $report = envRatchet($project)->check()->report();

    expect($report)->toContain(implode("\n", [
        '  app/Http/Controllers/HomeController.php:10  '.ENV_MESSAGE,
        '  app/Http/Controllers/HomeController.php:11  '.ENV_MESSAGE,
        '  app/Http/Controllers/HomeController.php:12  '.ENV_MESSAGE,
        '    (3 found in this file, the baseline allows 2; it cannot tell which of these are new)',
    ]))->not->toContain('Stale');
});

it('fails on a stale entry when debt is paid, so the baseline must shrink', function (): void {
    $project = temporaryProject();
    replaceInFile("{$project}/app/Http/Controllers/HomeController.php", "env('APP_DEBUG')", "config('app.debug')");

    $result = envRatchet($project)->check();

    expect($result->newOffenders)->toBe([])
        ->and($result->staleEntries)->toBe([[
            'file' => 'app/Http/Controllers/HomeController.php',
            'message' => ENV_MESSAGE,
            'found' => 1,
            'allowed' => 2,
        ]])
        ->and($result->report())->toBe(implode("\n", [
            'Ratchet NoDirectEnvCalls failed (baseline tests/ratchets/env.json).',
            '',
            'Stale baseline entries. This debt is paid; remove it from the baseline:',
            '  app/Http/Controllers/HomeController.php  '.ENV_MESSAGE,
            '    (the baseline allows 2, 1 remain)',
            '',
            'Run vendor/bin/pest --prune-ratchets to remove stale entries without adding any.',
        ]));
});

it('reports a deleted file as stale', function (): void {
    $project = temporaryProject();
    unlink("{$project}/app/Legacy/Old.php");

    expect(envRatchet($project)->check()->staleEntries)->toBe([[
        'file' => 'app/Legacy/Old.php',
        'message' => ENV_MESSAGE,
        'found' => 0,
        'allowed' => 1,
    ]]);
});

it('does not count commented-out code, so commenting an offender out makes its entry stale', function (): void {
    $project = temporaryProject();
    replaceInFile("{$project}/app/Legacy/Old.php", "return env('LEGACY_REGION');", "// return env('LEGACY_REGION');\n        return null;");

    $result = envRatchet($project)->check();

    expect($result->newOffenders)->toBe([])
        ->and(array_column($result->staleEntries, 'file'))->toBe(['app/Legacy/Old.php']);
});

it('reports new offenders and stale entries together', function (): void {
    $project = temporaryProject();
    unlink("{$project}/app/Legacy/Old.php");
    writeFile("{$project}/app/Fresh.php", "<?php\n\nreturn env('FRESH');\n");

    $result = envRatchet($project)->check();

    expect($result->newOffenders)->toHaveCount(1)
        ->and($result->staleEntries)->toHaveCount(1)
        ->and($result->report())->toContain('  app/Fresh.php:3  ')->toContain('  app/Legacy/Old.php  ');
});

it('is not disturbed when code moves within a file', function (): void {
    $project = temporaryProject();
    replaceInFile("{$project}/app/Http/Controllers/HomeController.php", "final class HomeController\n{", "final class HomeController\n{\n    // Some\n    // new\n    // lines.\n");

    expect(envRatchet($project)->check()->passed())->toBeTrue();
});

it('allows nothing without a baseline', function (): void {
    $result = Ratchet::rule(NoDirectEnvCalls::class)->root(temporaryProject())->in('app')->check();

    expect($result->passed())->toBeFalse()
        ->and($result->report())->toStartWith('Ratchet NoDirectEnvCalls failed (no baseline: every offender is new).')
        ->and($result->newOffenders)->toHaveCount(3);

    expect(Ratchet::rule(NoDebugCalls::class)->root(temporaryProject())->in('app')->assert()->passed())->toBeTrue();
});

it('updates the baseline to every current offender', function (): void {
    $project = temporaryProject();
    unlink("{$project}/tests/ratchets/env.json");
    writeFile("{$project}/app/Fresh.php", "<?php\n\nreturn env('FRESH');\n");

    $result = envRatchet($project)->assert(Mode::Update);

    expect($result->passed())->toBeTrue()
        ->and(json_decode((string) file_get_contents("{$project}/tests/ratchets/env.json"), true))->toBe([
            'rule' => NoDirectEnvCalls::class,
            'offenders' => [
                ['file' => 'app/Fresh.php', 'message' => ENV_MESSAGE, 'count' => 1],
                ['file' => 'app/Http/Controllers/HomeController.php', 'message' => ENV_MESSAGE, 'count' => 2],
                ['file' => 'app/Legacy/Old.php', 'message' => ENV_MESSAGE, 'count' => 1],
                ['file' => 'app/Services/Billing.php', 'message' => ENV_MESSAGE, 'count' => 1],
            ],
        ]);

    expect(envRatchet($project)->check()->passed())->toBeTrue();
});

it('writes the same bytes the committed baseline holds', function (): void {
    $project = temporaryProject();
    $before = file_get_contents(fixturePath('Project/tests/ratchets/env.json'));

    envRatchet($project)->update();

    expect(file_get_contents("{$project}/tests/ratchets/env.json"))->toBe($before);
});

it('prunes stale entries without adding new offenders, and still fails on them', function (): void {
    $project = temporaryProject();
    replaceInFile("{$project}/app/Http/Controllers/HomeController.php", "env('APP_DEBUG')", "config('app.debug')");
    unlink("{$project}/app/Legacy/Old.php");
    writeFile("{$project}/app/Fresh.php", "<?php\n\nreturn env('FRESH');\n");

    expect(fn () => envRatchet($project)->assert(Mode::Prune))
        ->toThrow(AssertionFailedError::class, 'app/Fresh.php:3');

    expect(json_decode((string) file_get_contents("{$project}/tests/ratchets/env.json"), true)['offenders'])->toBe([
        ['file' => 'app/Http/Controllers/HomeController.php', 'message' => ENV_MESSAGE, 'count' => 1],
        ['file' => 'app/Services/Billing.php', 'message' => ENV_MESSAGE, 'count' => 1],
    ]);

    unlink("{$project}/app/Fresh.php");

    expect(envRatchet($project)->check()->passed())->toBeTrue();
});

it('leaves an unchanged baseline file untouched', function (): void {
    $project = temporaryProject();
    $path = "{$project}/tests/ratchets/env.json";
    touch($path, 1_000_000_000);
    clearstatcache();

    envRatchet($project)->prune();
    clearstatcache();

    expect(filemtime($path))->toBe(1_000_000_000);
});

it('refuses to update or prune a ratchet without a baseline', function (string $method): void {
    expect(fn () => Ratchet::rule(NoDirectEnvCalls::class)->root(temporaryProject())->in('app')->{$method}())
        ->toThrow(RatchetException::class, 'it has none');
})->with(['update', 'prune']);

it('checks instead when asked to update a ratchet without a baseline', function (): void {
    expect(fn () => Ratchet::rule(NoDirectEnvCalls::class)->root(temporaryProject())->in('app')->assert(Mode::Update))
        ->toThrow(AssertionFailedError::class, 'no baseline');
});

it('fails loudly when the baseline file is missing', function (): void {
    $project = temporaryProject();
    unlink("{$project}/tests/ratchets/env.json");

    expect(fn () => envRatchet($project)->check())
        ->toThrow(RatchetException::class, 'env.json does not exist. Create it with: vendor/bin/pest --update-ratchets');
});

it('excludes paths', function (): void {
    $project = temporaryProject();

    $offenders = Ratchet::rule(NoDirectEnvCalls::class)->root($project)->in('app')->exclude('app/Legacy', 'app/Services/Billing.php')->offenders();

    expect(array_unique(array_map(static fn ($offender): string => $offender->file, $offenders)))
        ->toBe(['app/Http/Controllers/HomeController.php']);
});

it('accepts absolute paths inside the root and single files', function (): void {
    $project = temporaryProject();

    expect(Ratchet::rule(NoDirectEnvCalls::class)->root($project)->in("{$project}/app/Legacy/Old.php")->offenders())
        ->toHaveCount(1)
        ->sequence(fn ($offender) => $offender->file->toBe('app/Legacy/Old.php')->line->toBe(9));
});

it('rejects configuration that would check nothing or the wrong thing', function (Closure $configure, string $message): void {
    $project = temporaryProject();

    expect(fn () => $configure(Ratchet::rule(NoDirectEnvCalls::class)->root($project))->offenders())
        ->toThrow(RatchetException::class, $message);
})->with([
    'no paths' => [fn (Ratchet $ratchet) => $ratchet, 'needs at least one path'],
    'a missing path' => [fn (Ratchet $ratchet) => $ratchet->in('src'), "Path 'src' does not exist"],
    'an empty path' => [fn (Ratchet $ratchet) => $ratchet->in(' '), 'must not be empty'],
    'a path without PHP' => [fn (Ratchet $ratchet) => $ratchet->in('resources'), "Path 'resources' holds no .php files"],
    'a path outside the root' => [fn (Ratchet $ratchet) => $ratchet->in('..'), 'is outside the ratchet root'],
    'everything excluded' => [fn (Ratchet $ratchet) => $ratchet->in('app/Legacy')->exclude('app'), 'is excluded, so it would check nothing'],
    'a missing exclusion' => [fn (Ratchet $ratchet) => $ratchet->in('app')->exclude('app/Nope'), "Excluded path 'app/Nope' does not exist"],
]);

it('refuses a symlinked file that leads outside the root, unless it is excluded', function (): void {
    $project = temporaryProject();
    $outside = temporaryProject();
    symlink("{$outside}/app/Legacy/Old.php", "{$project}/app/Link.php");

    expect(fn () => Ratchet::rule(NoDirectEnvCalls::class)->root($project)->in('app')->offenders())
        ->toThrow(RatchetException::class, "File 'app/Link.php' resolves to {$outside}/app/Legacy/Old.php, outside the ratchet root");

    expect(Ratchet::rule(NoDirectEnvCalls::class)->root($project)->in('app')->exclude('app/Link.php')->offenders())
        ->toHaveCount(4);
});

it('follows a symlinked file that stays inside the root', function (): void {
    $project = temporaryProject();
    symlink("{$project}/app/Legacy/Old.php", "{$project}/app/Link.php");

    $files = array_map(static fn ($offender): string => $offender->file, Ratchet::rule(NoDirectEnvCalls::class)->root($project)->in('app')->offenders());

    expect($files)->toContain('app/Link.php')->toContain('app/Legacy/Old.php');

    // Excluding the link excludes the link, not the file it points to.
    $files = array_map(static fn ($offender): string => $offender->file, Ratchet::rule(NoDirectEnvCalls::class)->root($project)->in('app')->exclude('app/Link.php')->offenders());

    expect($files)->not->toContain('app/Link.php')->toContain('app/Legacy/Old.php');
});

it('refuses a path that is a symlink leading outside the root', function (): void {
    $project = temporaryProject();
    $outside = temporaryProject();
    symlink("{$outside}/app", "{$project}/linked");

    expect(fn () => Ratchet::rule(NoDirectEnvCalls::class)->root($project)->in('linked')->offenders())
        ->toThrow(RatchetException::class, "resolves to {$outside}/app/");
});

it('fails on a file PHP cannot parse rather than skipping it', function (): void {
    $project = temporaryProject();
    writeFile("{$project}/app/Broken.php", "<?php\n\nfunction (\n");

    expect(fn () => envRatchet($project)->check())
        ->toThrow(RatchetException::class, 'Could not parse app/Broken.php: Syntax error');
});

it('rejects a root that is not a directory', function (): void {
    expect(fn () => Ratchet::rule(NoDirectEnvCalls::class)->root(__FILE__))
        ->toThrow(RatchetException::class, 'is not a directory');
});

it('rejects a class that is not a rule', function (string $class, string $message): void {
    expect(fn () => Ratchet::rule($class))->toThrow(RatchetException::class, $message);
})->with([
    ['App\Missing', 'Rule class App\Missing does not exist.'],
    [stdClass::class, 'stdClass does not implement OffsetSmoker\PestRatchets\Rule.'],
]);

it('defaults the root to the working directory', function (): void {
    expect(Ratchet::rule(NoDebugCalls::class)->in('src')->check()->passed())->toBeTrue();
});
