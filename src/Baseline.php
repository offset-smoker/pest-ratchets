<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use JsonException;
use OffsetSmoker\PestRatchets\Exceptions\RatchetException;

/**
 * Today's allowed offenders for one rule: how many times each message may
 * occur in each file. Keyed on file and message, not line, so moving code
 * around a file does not disturb it.
 */
final readonly class Baseline
{
    /**
     * @param  class-string<Rule>  $rule
     * @param  array<string, array{file: string, message: string, count: int<1, max>}>  $entries  keyed by Offender::keyFor()
     */
    private function __construct(
        public string $rule,
        public array $entries,
    ) {}

    /**
     * @param  class-string<Rule>  $rule
     */
    public static function empty(string $rule): self
    {
        return new self($rule, []);
    }

    /**
     * @param  class-string<Rule>  $rule
     * @param  list<Offender>  $offenders
     */
    public static function fromOffenders(string $rule, array $offenders): self
    {
        $entries = [];

        foreach ($offenders as $offender) {
            $key = $offender->key();
            $count = ($entries[$key]['count'] ?? 0) + 1;
            $entries[$key] = ['file' => $offender->file, 'message' => $offender->message, 'count' => $count];
        }

        return new self($rule, self::sorted($entries));
    }

    /**
     * @param  class-string<Rule>  $rule
     */
    public static function load(string $path, string $rule): self
    {
        if (! is_file($path)) {
            throw new RatchetException("Baseline file {$path} does not exist. Create it with: vendor/bin/pest --update-ratchets");
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new RatchetException("Could not read baseline file {$path}.");
        }

        return self::fromJson($json, $rule, $path);
    }

    /**
     * @param  class-string<Rule>  $rule
     */
    public static function fromJson(string $json, string $rule, string $source = 'baseline'): self
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RatchetException("{$source} is not valid JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($data) || array_keys($data) !== ['rule', 'offenders']) {
            throw new RatchetException("{$source} must be a JSON object with exactly the keys \"rule\" and \"offenders\".");
        }

        if ($data['rule'] !== $rule) {
            $found = is_string($data['rule']) ? $data['rule'] : get_debug_type($data['rule']);

            throw new RatchetException("{$source} is the baseline for {$found}, not {$rule}. Point the ratchet at the right file, or regenerate it with vendor/bin/pest --update-ratchets.");
        }

        if (! is_array($data['offenders']) || ! array_is_list($data['offenders'])) {
            throw new RatchetException("{$source}: \"offenders\" must be a JSON array.");
        }

        $entries = [];

        foreach ($data['offenders'] as $index => $entry) {
            $where = "{$source}: offenders[{$index}]";

            if (! is_array($entry) || array_keys($entry) !== ['file', 'message', 'count']) {
                throw new RatchetException("{$where} must be an object with exactly the keys \"file\", \"message\" and \"count\".");
            }

            ['file' => $file, 'message' => $message, 'count' => $count] = $entry;

            if (! is_string($file) || ! self::isSafeRelativePath($file)) {
                throw new RatchetException("{$where}.file must be a path relative to the ratchet root, with forward slashes and no '..' segments.");
            }

            if (! is_string($message) || trim($message) === '') {
                throw new RatchetException("{$where}.message must be a non-empty string.");
            }

            if (! is_int($count) || $count < 1) {
                throw new RatchetException("{$where}.count must be a whole number of at least 1.");
            }

            $key = Offender::keyFor($file, $message);

            if (isset($entries[$key])) {
                throw new RatchetException("{$where} repeats the entry for {$file} \"{$message}\"; merge them into one count.");
            }

            $entries[$key] = ['file' => $file, 'message' => $message, 'count' => $count];
        }

        return new self($rule, self::sorted($entries));
    }

    /**
     * How many times a message is allowed in a file.
     */
    public function allowed(string $file, string $message): int
    {
        return $this->entries[Offender::keyFor($file, $message)]['count'] ?? 0;
    }

    /**
     * A baseline that keeps each entry at no more than the count now found,
     * dropping the entries that no longer offend. It never adds an entry or
     * raises a count.
     *
     * @param  list<Offender>  $offenders
     */
    public function prunedTo(array $offenders): self
    {
        $found = [];

        foreach ($offenders as $offender) {
            $found[$offender->key()] = ($found[$offender->key()] ?? 0) + 1;
        }

        $entries = [];

        foreach ($this->entries as $key => $entry) {
            $count = min($entry['count'], $found[$key] ?? 0);

            if ($count > 0) {
                $entries[$key] = [...$entry, 'count' => $count];
            }
        }

        return new self($this->rule, $entries);
    }

    public function total(): int
    {
        return array_sum(array_column($this->entries, 'count'));
    }

    public function toJson(): string
    {
        return json_encode(
            ['rule' => $this->rule, 'offenders' => array_values($this->entries)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        )."\n";
    }

    /**
     * Writes the baseline when its content differs from the file's. Returns
     * whether the file changed.
     */
    public function save(string $path): bool
    {
        $json = $this->toJson();

        if (is_file($path) && file_get_contents($path) === $json) {
            return false;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RatchetException("Could not create directory {$directory} for the baseline.");
        }

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RatchetException("Could not write baseline file {$path}.");
        }

        return true;
    }

    private static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('#^[A-Za-z]:#', $path) === 1) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array{file: string, message: string, count: int<1, max>}>  $entries
     * @return array<string, array{file: string, message: string, count: int<1, max>}>
     */
    private static function sorted(array $entries): array
    {
        uasort($entries, static fn (array $a, array $b): int => [$a['file'], $a['message']] <=> [$b['file'], $b['message']]);

        return $entries;
    }
}
