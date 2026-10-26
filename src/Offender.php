<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

/**
 * An offense found in a particular file, with the path relative to the root.
 */
final readonly class Offender
{
    public function __construct(
        public string $file,
        public int $line,
        public string $message,
    ) {}

    public function key(): string
    {
        return self::keyFor($this->file, $this->message);
    }

    public static function keyFor(string $file, string $message): string
    {
        return $file."\0".$message;
    }
}
