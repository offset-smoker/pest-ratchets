<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\PendingRatchet;
use OffsetSmoker\PestRatchets\Ratchet;
use OffsetSmoker\PestRatchets\Rule;
use Pest\TestSuite;

if (! function_exists('ratchet')) {
    /**
     * Defines a Pest test that holds the codebase to a rule and its baseline.
     * Call it at the top level of a test file, as you would arch():
     *
     *     ratchet(NoDirectEnvCalls::class)->in('app')->baseline('tests/ratchets/env.json');
     *
     * Paths resolve against the project root Pest runs from.
     *
     * @param  Rule|class-string<Rule>|string  $rule
     */
    function ratchet(Rule|string $rule, ?string $description = null): PendingRatchet
    {
        $ratchet = Ratchet::rule($rule)->root(TestSuite::getInstance()->rootPath);

        $test = test($description ?? 'ratchet '.$ratchet->ruleName(), function () use ($ratchet): void {
            $ratchet->assert();
        });

        return new PendingRatchet($ratchet, $test);
    }
}
