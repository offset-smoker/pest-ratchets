<?php

declare(strict_types=1);

/*
 * Supply-chain hygiene for CI: every action is pinned to a full commit SHA
 * with the version it is in a comment, and Dependabot keeps both actions
 * and Composer packages current.
 */

it('pins every GitHub Action to a full commit SHA with its version', function (): void {
    $workflows = glob(dirname(__DIR__, 2).'/.github/workflows/*.yml') ?: [];
    $uses = [];

    foreach ($workflows as $workflow) {
        foreach (file($workflow, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            if (preg_match('/^\s*(?:-\s+)?uses:\s*(\S+)(.*)$/', $line, $match) === 1) {
                $uses[basename($workflow).':'.($number + 1)] = trim($match[1].$match[2]);
            }
        }
    }

    expect($uses)->not->toBeEmpty();

    foreach ($uses as $where => $use) {
        expect($use)->toMatch('/^[\w.-]+\/[\w.\/-]+@[0-9a-f]{40} # v?\d+\.\d+\.\d+$/', "{$where} uses {$use}");
    }
});

it('has Dependabot watch GitHub Actions and Composer', function (): void {
    $config = dirname(__DIR__, 2).'/.github/dependabot.yml';

    expect($config)->toBeFile()
        ->and((string) file_get_contents($config))
        ->toContain('package-ecosystem: github-actions')
        ->toContain('package-ecosystem: composer');
});
