<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Exceptions\RatchetException;
use OffsetSmoker\PestRatchets\Mode;
use OffsetSmoker\PestRatchets\Plugin;

it('checks by default', function (): void {
    expect(Plugin::mode())->toBe(Mode::Check);
});

it('takes the mode from the command line and removes its option', function (string $option, Mode $mode): void {
    $arguments = (new Plugin)->handleArguments(['vendor/bin/pest', $option, '--filter=ratchet']);

    expect($arguments)->toBe(['vendor/bin/pest', '--filter=ratchet'])
        ->and(Plugin::mode())->toBe($mode);
})->with([
    ['--update-ratchets', Mode::Update],
    ['--prune-ratchets', Mode::Prune],
]);

it('leaves other arguments alone', function (): void {
    expect((new Plugin)->handleArguments(['vendor/bin/pest', '--parallel']))->toBe(['vendor/bin/pest', '--parallel'])
        ->and(Plugin::mode())->toBe(Mode::Check);
});

it('refuses both options at once', function (): void {
    expect(fn () => (new Plugin)->handleArguments(['pest', '--update-ratchets', '--prune-ratchets']))
        ->toThrow(RatchetException::class, 'not both');
});

it('reads the PEST_RATCHETS environment variable', function (string $value, Mode $mode): void {
    putenv("PEST_RATCHETS={$value}");

    expect(Plugin::mode())->toBe($mode);
})->with([
    ['update', Mode::Update],
    [' Prune ', Mode::Prune],
    ['check', Mode::Check],
    ['', Mode::Check],
]);

it('lets the command line win over the environment', function (): void {
    putenv('PEST_RATCHETS=update');
    (new Plugin)->handleArguments(['pest', '--prune-ratchets']);

    expect(Plugin::mode())->toBe(Mode::Prune);
});

it('rejects an unknown mode', function (): void {
    putenv('PEST_RATCHETS=grow');

    expect(fn () => Plugin::mode())->toThrow(RatchetException::class, "Unknown ratchet mode 'grow'");
});
