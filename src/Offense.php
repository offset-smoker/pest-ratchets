<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use OffsetSmoker\PestRatchets\Exceptions\RatchetException;

final readonly class Offense
{
    public function __construct(
        public int $line,
        public string $message,
    ) {
        if ($line < 1) {
            throw new RatchetException("An offense must be on line 1 or later, got line {$line}.");
        }

        if (trim($message) === '' || preg_match('/[\r\n]/', $message) === 1) {
            throw new RatchetException('An offense message must be a single non-empty line.');
        }
    }
}
