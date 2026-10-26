<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use Pest\PendingCalls\TestCall;

/**
 * Returned by the top-level ratchet() function. It has already registered a
 * Pest test that asserts the ratchet; the methods here configure it before
 * the test runs.
 */
final readonly class PendingRatchet
{
    public function __construct(
        private Ratchet $ratchet,
        private TestCall $test,
    ) {}

    public function in(string ...$paths): self
    {
        $this->ratchet->in(...$paths);

        return $this;
    }

    public function exclude(string ...$paths): self
    {
        $this->ratchet->exclude(...$paths);

        return $this;
    }

    public function baseline(string $path): self
    {
        $this->ratchet->baseline($path);

        return $this;
    }

    public function root(string $path): self
    {
        $this->ratchet->root($path);

        return $this;
    }

    public function group(string ...$groups): self
    {
        $this->test->group(...$groups);

        return $this;
    }
}
