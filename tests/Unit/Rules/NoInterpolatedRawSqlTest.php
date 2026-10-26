<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Rules\NoInterpolatedRawSql;

it('flags raw SQL built from variables', function (): void {
    $message = 'SQL built from interpolated or concatenated values; pass them as ? bindings';

    expect(offendersIn(NoInterpolatedRawSql::class, fixturePath('Rules/RawSql'), 'Queries.php'))->toBe([
        "Queries.php:14 DB::raw() {$message}",
        "Queries.php:15 DB::select() {$message}",
        "Queries.php:16 ->whereRaw() {$message}",
        "Queries.php:17 ->orderByRaw() {$message}",
        "Queries.php:18 whereRaw() {$message}",
        "Queries.php:19 DB::statement() {$message}",
        "Queries.php:20 ->havingRaw() {$message}",
        "Queries.php:21 ->selectRaw() {$message}",
    ]);
});

it('leaves bindings, literals, constants, comments and strings alone', function (): void {
    $offenders = offendersIn(NoInterpolatedRawSql::class, fixturePath('Rules/RawSql'), 'Queries.php');

    expect(array_filter($offenders, static fn (string $offender): bool => (int) explode(':', $offender)[1] > 21))->toBe([]);
});

it('only treats the configured classes as the DB facade', function (): void {
    expect(offendersIn(NoInterpolatedRawSql::class, fixturePath('Rules/RawSql'), 'NotTheFacade.php'))->toBe([]);

    expect(offendersIn(new NoInterpolatedRawSql(['App\DB']), fixturePath('Rules/RawSql'), 'NotTheFacade.php'))->toBe([
        'NotTheFacade.php:9 DB::select() SQL built from interpolated or concatenated values; pass them as ? bindings',
    ]);
});

it('flags raw SQL sent through DB::connection() and built into a new Expression', function (): void {
    $message = 'SQL built from interpolated or concatenated values; pass them as ? bindings';

    expect(offendersIn(NoInterpolatedRawSql::class, fixturePath('Rules/RawSql'), 'Connections.php'))->toBe([
        "Connections.php:12 DB::connection()->select() {$message}",
        "Connections.php:13 DB::connection()->statement() {$message}",
        "Connections.php:14 DB::connection()->unprepared() {$message}",
        "Connections.php:15 DB::connection()->raw() {$message}",
        "Connections.php:16 new Expression() {$message}",
        "Connections.php:17 new Expression() {$message}",
    ]);
});
