<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use OffsetSmoker\PestRatchets\Exceptions\RatchetException;
use PHPUnit\Framework\Assert;

/**
 * One rule, the paths it scans and the baseline it holds them to.
 *
 *     Ratchet::rule(NoDirectEnvCalls::class)
 *         ->in('app', 'routes')
 *         ->baseline('tests/ratchets/env.json')
 *         ->assert();
 */
final class Ratchet
{
    /** @var list<string> */
    private array $paths = [];

    /** @var list<string> */
    private array $excludes = [];

    private ?string $baseline = null;

    private string $root;

    private function __construct(private readonly Rule $rule)
    {
        $cwd = getcwd();
        $this->root($cwd === false ? '.' : $cwd);
    }

    /**
     * @param  Rule|class-string<Rule>|string  $rule  a rule, or the class name of one with no required constructor arguments
     */
    public static function rule(Rule|string $rule): self
    {
        if ($rule instanceof Rule) {
            return new self($rule);
        }

        if (! class_exists($rule)) {
            throw new RatchetException("Rule class {$rule} does not exist.");
        }

        if (! is_subclass_of($rule, Rule::class)) {
            throw new RatchetException("{$rule} does not implement ".Rule::class.'.');
        }

        return new self(new $rule);
    }

    /**
     * The directory relative paths resolve against, and that baseline paths
     * are recorded from. Defaults to the working directory.
     */
    public function root(string $path): self
    {
        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            throw new RatchetException("Ratchet root {$path} is not a directory.");
        }

        $this->root = rtrim(str_replace('\\', '/', $real), '/');

        return $this;
    }

    /**
     * Directories (scanned recursively for .php files) or single files.
     */
    public function in(string ...$paths): self
    {
        $this->paths = [...$this->paths, ...array_values($paths)];

        return $this;
    }

    public function exclude(string ...$paths): self
    {
        $this->excludes = [...$this->excludes, ...array_values($paths)];

        return $this;
    }

    /**
     * The JSON file of allowed offenders, relative to the root. Without one,
     * any offender fails the ratchet.
     */
    public function baseline(string $path): self
    {
        if (trim($path) === '') {
            throw new RatchetException('The baseline path must not be empty.');
        }

        $this->baseline = $path;

        return $this;
    }

    public function ruleName(): string
    {
        $class = $this->rule::class;
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * @return list<Offender>
     */
    public function offenders(): array
    {
        return (new Analyzer($this->root))->analyse($this->rule, $this->paths, $this->excludes);
    }

    public function check(): RatchetResult
    {
        return $this->compare($this->offenders(), $this->loadBaseline());
    }

    /**
     * Runs the ratchet in the given mode (by default the one chosen on the
     * Pest command line or in PEST_RATCHETS) and fails the current test
     * unless it passes.
     */
    public function assert(?Mode $mode = null): RatchetResult
    {
        $mode ??= Plugin::mode();

        // Without a baseline there is nothing to update or prune.
        $result = $this->baseline === null ? $this->check() : match ($mode) {
            Mode::Check => $this->check(),
            Mode::Update => $this->update(),
            Mode::Prune => $this->prune(),
        };

        Assert::assertTrue($result->passed(), $result->report());

        return $result;
    }

    /**
     * Writes every current offender to the baseline. This is the only way
     * the baseline grows, so run it deliberately.
     */
    public function update(): RatchetResult
    {
        $path = $this->requireBaselinePath('update');
        $offenders = $this->offenders();
        $baseline = Baseline::fromOffenders($this->rule::class, $offenders);
        $baseline->save($path);

        return $this->compare($offenders, $baseline);
    }

    /**
     * Removes stale entries from the baseline, never adding any, and returns
     * the comparison against the pruned baseline, which still fails on new
     * offenders.
     */
    public function prune(): RatchetResult
    {
        $path = $this->requireBaselinePath('prune');
        $offenders = $this->offenders();
        $baseline = $this->loadBaseline()->prunedTo($offenders);
        $baseline->save($path);

        return $this->compare($offenders, $baseline);
    }

    /**
     * @param  list<Offender>  $offenders
     */
    private function compare(array $offenders, Baseline $baseline): RatchetResult
    {
        return RatchetResult::compare($this->ruleName(), $offenders, $baseline, $this->baseline);
    }

    private function loadBaseline(): Baseline
    {
        if ($this->baseline === null) {
            return Baseline::empty($this->rule::class);
        }

        return Baseline::load($this->baselinePath(), $this->rule::class);
    }

    private function requireBaselinePath(string $action): string
    {
        if ($this->baseline === null) {
            throw new RatchetException("Cannot {$action} the baseline of ratchet {$this->ruleName()}: it has none. Add ->baseline('tests/ratchets/....json').");
        }

        return $this->baselinePath();
    }

    private function baselinePath(): string
    {
        $path = str_replace('\\', '/', (string) $this->baseline);

        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1
            ? $path
            : $this->root.'/'.$path;
    }
}
