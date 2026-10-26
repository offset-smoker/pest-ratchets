<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Baseline;
use OffsetSmoker\PestRatchets\Exceptions\RatchetException;
use OffsetSmoker\PestRatchets\Offender;
use OffsetSmoker\PestRatchets\Rules\NoDebugCalls;
use OffsetSmoker\PestRatchets\Rules\NoDirectEnvCalls;

function baselineJson(mixed $offenders, string $rule = NoDebugCalls::class): string
{
    return json_encode(['rule' => $rule, 'offenders' => $offenders], JSON_THROW_ON_ERROR);
}

it('counts offenders per file and message, sorted', function (): void {
    $baseline = Baseline::fromOffenders(NoDebugCalls::class, [
        new Offender('b.php', 3, 'debug call dd() left in code'),
        new Offender('a.php', 9, 'debug call dump() left in code'),
        new Offender('b.php', 1, 'debug call dd() left in code'),
        new Offender('a.php', 2, 'debug call dd() left in code'),
    ]);

    expect(array_values($baseline->entries))->toBe([
        ['file' => 'a.php', 'message' => 'debug call dd() left in code', 'count' => 1],
        ['file' => 'a.php', 'message' => 'debug call dump() left in code', 'count' => 1],
        ['file' => 'b.php', 'message' => 'debug call dd() left in code', 'count' => 2],
    ])
        ->and($baseline->total())->toBe(4)
        ->and($baseline->allowed('b.php', 'debug call dd() left in code'))->toBe(2)
        ->and($baseline->allowed('c.php', 'debug call dd() left in code'))->toBe(0);
});

it('round-trips through JSON', function (): void {
    $baseline = Baseline::fromOffenders(NoDebugCalls::class, [new Offender('app/Ünïcode.php', 3, 'debug call dd() left in code')]);

    expect(Baseline::fromJson($baseline->toJson(), NoDebugCalls::class)->entries)->toBe($baseline->entries)
        ->and($baseline->toJson())->toEndWith("}\n")->toContain('app/Ünïcode.php');
});

it('reads an empty baseline', function (): void {
    expect(Baseline::fromJson(baselineJson([]), NoDebugCalls::class)->entries)->toBe([]);
});

it('never adds or raises entries when pruned', function (): void {
    $baseline = Baseline::fromJson(baselineJson([
        ['file' => 'a.php', 'message' => 'm', 'count' => 3],
        ['file' => 'b.php', 'message' => 'm', 'count' => 1],
    ]), NoDebugCalls::class);

    $pruned = $baseline->prunedTo([
        new Offender('a.php', 1, 'm'),
        new Offender('a.php', 2, 'm'),
        new Offender('c.php', 1, 'm'),
        new Offender('c.php', 2, 'm'),
    ]);

    expect(array_values($pruned->entries))->toBe([['file' => 'a.php', 'message' => 'm', 'count' => 2]]);
});

it('rejects a malformed baseline with a message saying what is wrong', function (string $json, string $message): void {
    expect(fn () => Baseline::fromJson($json, NoDebugCalls::class, 'env.json'))
        ->toThrow(RatchetException::class, $message);
})->with([
    'not JSON' => ['{', 'env.json is not valid JSON'],
    'a list' => ['[]', 'must be a JSON object with exactly the keys "rule" and "offenders"'],
    'extra keys' => [json_encode(['rule' => NoDebugCalls::class, 'offenders' => [], 'x' => 1]), 'exactly the keys "rule" and "offenders"'],
    'another rule' => [baselineJson([], NoDirectEnvCalls::class), 'is the baseline for OffsetSmoker\PestRatchets\Rules\NoDirectEnvCalls, not OffsetSmoker\PestRatchets\Rules\NoDebugCalls'],
    'a non-string rule' => [json_encode(['rule' => 5, 'offenders' => []]), 'is the baseline for int'],
    'offenders as an object' => [baselineJson(['a' => 1]), '"offenders" must be a JSON array'],
    'an entry with missing keys' => [baselineJson([['file' => 'a.php']]), 'offenders[0] must be an object with exactly the keys "file", "message" and "count"'],
    'an absolute file' => [baselineJson([['file' => '/etc/passwd', 'message' => 'm', 'count' => 1]]), 'offenders[0].file must be a path relative'],
    'a parent segment' => [baselineJson([['file' => 'app/../../x.php', 'message' => 'm', 'count' => 1]]), 'offenders[0].file must be a path relative'],
    'a backslash' => [baselineJson([['file' => 'app\\x.php', 'message' => 'm', 'count' => 1]]), 'offenders[0].file must be a path relative'],
    'a drive letter' => [baselineJson([['file' => 'C:x.php', 'message' => 'm', 'count' => 1]]), 'offenders[0].file must be a path relative'],
    'an empty message' => [baselineJson([['file' => 'a.php', 'message' => ' ', 'count' => 1]]), 'offenders[0].message must be a non-empty string'],
    'a zero count' => [baselineJson([['file' => 'a.php', 'message' => 'm', 'count' => 0]]), 'offenders[0].count must be a whole number of at least 1'],
    'a string count' => [baselineJson([['file' => 'a.php', 'message' => 'm', 'count' => '2']]), 'offenders[0].count must be a whole number of at least 1'],
    'a duplicate' => [baselineJson([['file' => 'a.php', 'message' => 'm', 'count' => 1], ['file' => 'a.php', 'message' => 'm', 'count' => 2]]), 'offenders[1] repeats the entry for a.php "m"'],
]);
