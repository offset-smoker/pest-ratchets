<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Rules\NoDebugCalls;

it('flags dd, dump and ray calls and debug methods, but not comments, strings or other calls', function (): void {
    expect(offendersIn(NoDebugCalls::class, fixturePath('Rules/Debug')))->toBe([
        'Debugging.php:9 debug call dd() left in code',
        'Debugging.php:10 debug call dump() left in code',
        'Debugging.php:11 debug call ray() left in code',
        'Debugging.php:12 debug call ->dd() left in code',
        'Debugging.php:13 debug call ->dumpRawSql() left in code',
        'Debugging.php:14 debug call ->ddRawSql() left in code',
        'Debugging.php:15 debug call ->DUMP() left in code',
    ]);
});

it('can flag functions only, or a different list', function (): void {
    expect(offendersIn(new NoDebugCalls(methods: []), fixturePath('Rules/Debug')))->toBe([
        'Debugging.php:9 debug call dd() left in code',
        'Debugging.php:10 debug call dump() left in code',
        'Debugging.php:11 debug call ray() left in code',
    ]);

    expect(offendersIn(new NoDebugCalls(functions: ['var_dump'], methods: ['export']), fixturePath('Rules/Debug')))->toBe([
        'Debugging.php:24 debug call ->export() left in code',
        'Debugging.php:25 debug call var_dump() left in code',
    ]);
});
