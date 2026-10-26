<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Rules\NoDirectEnvCalls;

it('flags env() and Env::get() outside config/, and nothing in comments, strings or the config directory', function (): void {
    expect(offendersIn(NoDirectEnvCalls::class, fixturePath('Rules/Env')))->toBe([
        'app/Mailer.php:11 env() called outside config/; read the value through config() instead',
        'app/Mailer.php:16 Env::get() called outside config/; read the value through config() instead',
        'app/Mailer.php:16 env() called outside config/; read the value through config() instead',
    ]);
});

it('ignores functions and methods that only share the name', function (): void {
    expect(offendersIn(NoDirectEnvCalls::class, fixturePath('Rules/Env'), 'app/Lookalikes.php'))->toBe([]);
});

it('takes other allowed paths', function (): void {
    $rule = new NoDirectEnvCalls(['config', 'app/Mailer.php']);

    expect(offendersIn($rule, fixturePath('Rules/Env')))->toBe([]);

    $rule = new NoDirectEnvCalls(['bootstrap']);

    expect(offendersIn($rule, fixturePath('Rules/Env')))->toBe([
        'app/Mailer.php:11 env() called outside bootstrap/; read the value through config() instead',
        'app/Mailer.php:16 Env::get() called outside bootstrap/; read the value through config() instead',
        'app/Mailer.php:16 env() called outside bootstrap/; read the value through config() instead',
        'config/services.php:4 env() called outside bootstrap/; read the value through config() instead',
    ]);
});

it('does not treat a directory that only starts with the same letters as allowed', function (): void {
    $project = temporaryProject('Rules/Env');
    writeFile("{$project}/configuration/load.php", "<?php\n\nreturn env('X');\n");

    expect(offendersIn(NoDirectEnvCalls::class, $project, 'configuration'))->toBe([
        'configuration/load.php:3 env() called outside config/; read the value through config() instead',
    ]);
});
