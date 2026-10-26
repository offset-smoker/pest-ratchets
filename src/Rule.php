<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

/**
 * A rule inspects one parsed PHP file and reports what offends in it.
 *
 * The message of an Offense identifies it in the baseline together with the
 * file, so it must be stable: never put a line number, a timestamp or
 * anything else that changes when unrelated code moves into it.
 */
interface Rule
{
    /**
     * @return list<Offense>
     */
    public function check(SourceFile $file): array;
}
