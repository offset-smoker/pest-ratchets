<?php

declare(strict_types=1);

/*
 * These run Pest itself, in a separate process, on a copy of the fixture
 * project, to prove the parts a test inside this suite cannot: that
 * ratchet() registers a test that fails the run, and that the command-line
 * options reach it.
 *
 * Pest takes its root from the vendor directory it runs from, so the nested
 * run's root is this repository and its ratchet names the project as root.
 */

/**
 * @return array{exit: int, output: string}
 */
function runPest(string $project, array $arguments = [], array $environment = []): array
{
    $root = dirname(__DIR__, 2);

    writeFile("{$project}/phpunit.xml", <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit bootstrap="{$root}/vendor/autoload.php" cacheDirectory=".phpunit.cache">
            <testsuites>
                <testsuite name="Ratchets">
                    <directory>tests/Ratchets</directory>
                </testsuite>
            </testsuites>
        </phpunit>
        XML);

    writeFile("{$project}/tests/Ratchets/RatchetsTest.php", <<<'PHP'
        <?php

        use OffsetSmoker\PestRatchets\Rules\NoDirectEnvCalls;

        ratchet(NoDirectEnvCalls::class)->root(dirname(__DIR__, 2))->in('app', 'config')->baseline('tests/ratchets/env.json');
        PHP);

    $environment = [...getenv(), 'PEST_RATCHETS' => '', ...$environment];
    unset($environment['PARATEST']);

    $process = proc_open(
        [PHP_BINARY, "{$root}/vendor/bin/pest", '--colors=never', "--configuration={$project}/phpunit.xml", ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $project,
        $environment,
    );

    expect($process)->toBeResource();

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return ['exit' => proc_close($process), 'output' => $output];
}

function baselineFiles(string $project): array
{
    $baseline = json_decode((string) file_get_contents("{$project}/tests/ratchets/env.json"), true);

    return array_column($baseline['offenders'], 'count', 'file');
}

it('passes a run whose offenders match the baseline', function (): void {
    $run = runPest(temporaryProject());

    expect($run['output'])->toContain('ratchet NoDirectEnvCalls')->toContain('1 passed')
        ->and($run['exit'])->toBe(0);
});

it('fails the run on a new offender, naming the file and line', function (): void {
    $project = temporaryProject();
    writeFile("{$project}/app/Fresh.php", "<?php\n\nreturn env('FRESH');\n");

    $run = runPest($project);

    expect($run['output'])->toContain('app/Fresh.php:3  env() called outside config/')
        ->and($run['exit'])->toBe(1);
});

it('fails the run on a stale entry', function (): void {
    $project = temporaryProject();
    unlink("{$project}/app/Legacy/Old.php");

    $run = runPest($project);

    expect($run['output'])->toContain('Stale baseline entries')->toContain('app/Legacy/Old.php')
        ->and($run['exit'])->toBe(1);
});

it('regenerates the baseline with --update-ratchets', function (): void {
    $project = temporaryProject();
    writeFile("{$project}/app/Fresh.php", "<?php\n\nreturn env('FRESH');\n");

    expect(runPest($project, ['--update-ratchets'])['exit'])->toBe(0)
        ->and(baselineFiles($project))->toHaveKey('app/Fresh.php', 1)
        ->and(runPest($project)['exit'])->toBe(0);
});

it('regenerates the baseline when PEST_RATCHETS=update', function (): void {
    $project = temporaryProject();
    writeFile("{$project}/app/Fresh.php", "<?php\n\nreturn env('FRESH');\n");

    expect(runPest($project, [], ['PEST_RATCHETS' => 'update'])['exit'])->toBe(0)
        ->and(baselineFiles($project))->toHaveKey('app/Fresh.php', 1);
});

it('prunes stale entries with --prune-ratchets, and still fails on new offenders', function (): void {
    $project = temporaryProject();
    unlink("{$project}/app/Legacy/Old.php");
    writeFile("{$project}/app/Fresh.php", "<?php\n\nreturn env('FRESH');\n");

    $run = runPest($project, ['--prune-ratchets']);

    expect($run['exit'])->toBe(1)
        ->and($run['output'])->toContain('app/Fresh.php:3')->not->toContain('Stale baseline entries')
        ->and(baselineFiles($project))->toBe([
            'app/Http/Controllers/HomeController.php' => 2,
            'app/Services/Billing.php' => 1,
        ]);
});
