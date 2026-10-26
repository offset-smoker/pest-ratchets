<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use OffsetSmoker\PestRatchets\Exceptions\RatchetException;

enum Mode: string
{
    /** Fail on new offenders and on stale baseline entries. */
    case Check = 'check';

    /** Write every current offender to the baseline, then pass. */
    case Update = 'update';

    /** Remove stale entries from the baseline (never add any), then check. */
    case Prune = 'prune';

    public static function parse(string $value): self
    {
        $mode = self::tryFrom(strtolower(trim($value)));

        if ($mode === null) {
            throw new RatchetException("Unknown ratchet mode '{$value}'; expected check, update or prune.");
        }

        return $mode;
    }
}
