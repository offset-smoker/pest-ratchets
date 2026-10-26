<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Rules\ModelsDeclareFillable;
use OffsetSmoker\PestRatchets\Rules\NoDebugCalls;
use OffsetSmoker\PestRatchets\Rules\NoDirectEnvCalls;
use OffsetSmoker\PestRatchets\Rules\NoInterpolatedRawSql;

// The top-level function defines a test, as arch() does. These hold the
// committed fixture project to its committed baseline.
ratchet(NoDirectEnvCalls::class)
    ->root(fixturePath('Project'))
    ->in('app', 'config')
    ->baseline('tests/ratchets/env.json');

// And this package to its own rules, with no baseline at all.
ratchet(new NoDebugCalls, 'this package leaves no debug calls')->in('src')->group('dogfood');
ratchet(NoDirectEnvCalls::class, 'this package never calls env()')->in('src')->group('dogfood');
ratchet(NoInterpolatedRawSql::class, 'this package builds no raw SQL')->in('src')->group('dogfood');
ratchet(ModelsDeclareFillable::class, 'this package has no unguarded models')->in('src', 'tests')->exclude('tests/Fixtures')->group('dogfood');
