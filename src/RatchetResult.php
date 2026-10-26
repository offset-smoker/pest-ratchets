<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

/**
 * The outcome of comparing what a rule found with what the baseline allows.
 */
final readonly class RatchetResult
{
    /**
     * @param  list<array{file: string, message: string, lines: list<int>, allowed: int}>  $newOffenders  groups where more offenders were found than the baseline allows
     * @param  list<array{file: string, message: string, found: int, allowed: int}>  $staleEntries  entries that allow more offenders than remain
     */
    private function __construct(
        public string $ruleName,
        public ?string $baselinePath,
        public array $newOffenders,
        public array $staleEntries,
        public int $offenderCount,
    ) {}

    /**
     * @param  list<Offender>  $offenders
     */
    public static function compare(string $ruleName, array $offenders, Baseline $baseline, ?string $baselinePath): self
    {
        /** @var array<string, array{file: string, message: string, lines: list<int>}> $groups */
        $groups = [];

        foreach ($offenders as $offender) {
            $groups[$offender->key()] ??= ['file' => $offender->file, 'message' => $offender->message, 'lines' => []];
            $groups[$offender->key()]['lines'][] = $offender->line;
        }

        $new = [];

        foreach ($groups as $group) {
            $allowed = $baseline->allowed($group['file'], $group['message']);

            if (count($group['lines']) > $allowed) {
                $new[] = [...$group, 'allowed' => $allowed];
            }
        }

        $stale = [];

        foreach ($baseline->entries as $key => $entry) {
            $found = count($groups[$key]['lines'] ?? []);

            if ($found < $entry['count']) {
                $stale[] = ['file' => $entry['file'], 'message' => $entry['message'], 'found' => $found, 'allowed' => $entry['count']];
            }
        }

        return new self($ruleName, $baselinePath, $new, $stale, count($offenders));
    }

    public function passed(): bool
    {
        return $this->newOffenders === [] && $this->staleEntries === [];
    }

    public function report(): string
    {
        if ($this->passed()) {
            return "Ratchet {$this->ruleName} passed.";
        }

        $lines = [$this->baselinePath === null
            ? "Ratchet {$this->ruleName} failed (no baseline: every offender is new)."
            : "Ratchet {$this->ruleName} failed (baseline {$this->baselinePath}).", ];

        if ($this->newOffenders !== []) {
            $lines[] = '';
            $lines[] = 'New offenders. Fix them; the baseline may only shrink:';

            foreach ($this->newOffenders as $group) {
                foreach ($group['lines'] as $line) {
                    $lines[] = "  {$group['file']}:{$line}  {$group['message']}";
                }

                if ($group['allowed'] > 0) {
                    $found = count($group['lines']);
                    $lines[] = "    ({$found} found in this file, the baseline allows {$group['allowed']}; it cannot tell which of these are new)";
                }
            }
        }

        if ($this->staleEntries !== []) {
            $lines[] = '';
            $lines[] = 'Stale baseline entries. This debt is paid; remove it from the baseline:';

            foreach ($this->staleEntries as $entry) {
                $lines[] = "  {$entry['file']}  {$entry['message']}";
                $lines[] = "    (the baseline allows {$entry['allowed']}, {$entry['found']} remain)";
            }

            $lines[] = '';
            $lines[] = 'Run vendor/bin/pest --prune-ratchets to remove stale entries without adding any.';
        }

        return implode("\n", $lines);
    }
}
