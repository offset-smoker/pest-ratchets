<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Exceptions\RatchetException;
use OffsetSmoker\PestRatchets\Offense;
use OffsetSmoker\PestRatchets\Ratchet;
use OffsetSmoker\PestRatchets\Rule;
use OffsetSmoker\PestRatchets\SourceFile;

it('rejects offenses a baseline could not hold', function (int $line, string $message, string $error): void {
    expect(fn () => new Offense($line, $message))->toThrow(RatchetException::class, $error);
})->with([
    [0, 'm', 'must be on line 1 or later'],
    [1, '', 'single non-empty line'],
    [1, "two\nlines", 'single non-empty line'],
]);

it('runs a custom rule written against the documented interface', function (): void {
    $rule = new class implements Rule
    {
        public function check(SourceFile $file): array
        {
            return array_map(
                static fn ($function): Offense => new Offense($function->getStartLine(), "function {$function->name->toString()}() declared outside a class"),
                $file->findInstanceOf(PhpParser\Node\Stmt\Function_::class),
            );
        }
    };

    $project = temporaryProject();
    writeFile("{$project}/app/helpers.php", "<?php\n\n// function commented() {}\n\nfunction helper(): void {}\n");

    expect(Ratchet::rule($rule)->root($project)->in('app')->check()->report())
        ->toContain('  app/helpers.php:5  function helper() declared outside a class')
        ->not->toContain('commented');
});

it('tells whether a file is under a path', function (): void {
    $file = new SourceFile('app/Http/Kernel.php', '/x/app/Http/Kernel.php', []);

    expect($file->isUnder('app'))->toBeTrue()
        ->and($file->isUnder('app/Http/'))->toBeTrue()
        ->and($file->isUnder('app/Http/Kernel.php'))->toBeTrue()
        ->and($file->isUnder('ap'))->toBeFalse()
        ->and($file->isUnder('config', 'routes'))->toBeFalse()
        ->and($file->isUnder('.'))->toBeFalse()
        ->and($file->isUnder(''))->toBeTrue();
});
