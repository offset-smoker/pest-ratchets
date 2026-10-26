<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Plugin;
use OffsetSmoker\PestRatchets\Ratchet;
use OffsetSmoker\PestRatchets\Rule;

/*
 * Every test starts in check mode and off CI, whatever the environment
 * says, and leaves no temporary project behind.
 */
pest()->beforeEach(function (): void {
    $GLOBALS['pest-ratchets-ci'] = getenv('CI');
    Plugin::reset();
    putenv(Plugin::ENVIRONMENT_VARIABLE);
    putenv('CI');
})->afterEach(function (): void {
    Plugin::reset();
    putenv(Plugin::ENVIRONMENT_VARIABLE);
    $ci = $GLOBALS['pest-ratchets-ci'] ?? false;
    putenv(is_string($ci) ? "CI={$ci}" : 'CI');
    removeTemporaryProjects();
});

function fixturePath(string $path = ''): string
{
    return __DIR__.'/Fixtures'.($path === '' ? '' : '/'.$path);
}

/**
 * "file:line message" for every offender a rule finds under a directory.
 *
 * @return list<string>
 */
function offendersIn(Rule|string $rule, string $root, string ...$paths): array
{
    $ratchet = Ratchet::rule($rule)->root($root)->in(...($paths === [] ? ['.'] : $paths));

    return array_map(
        static fn ($offender): string => "{$offender->file}:{$offender->line} {$offender->message}",
        $ratchet->offenders(),
    );
}

/**
 * Copies a fixture directory to a fresh directory under build/tmp and
 * returns its path. It stays inside this repository so that a nested Pest
 * run (see EndToEndTest) can name test classes after it.
 */
function temporaryProject(string $fixture = 'Project'): string
{
    $target = dirname(__DIR__).'/build/tmp/Project'.bin2hex(random_bytes(6));
    mkdir($target, 0o755, true);
    $target = (string) realpath($target);

    $source = fixturePath($fixture);
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($items as $item) {
        $destination = $target.substr($item->getPathname(), strlen($source));
        $item->isDir() ? mkdir($destination) : copy($item->getPathname(), $destination);
    }

    $GLOBALS['pest-ratchets-temporary'][] = $target;

    return $target;
}

function writeFile(string $path, string $contents): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o755, true);
    }

    file_put_contents($path, $contents);
}

function replaceInFile(string $path, string $search, string $replace): void
{
    $contents = file_get_contents($path);
    expect($contents)->toContain($search);
    file_put_contents($path, str_replace($search, $replace, $contents));
}

function removeTemporaryProjects(): void
{
    foreach ($GLOBALS['pest-ratchets-temporary'] ?? [] as $directory) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }

    $GLOBALS['pest-ratchets-temporary'] = [];
}
