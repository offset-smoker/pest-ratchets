<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use OffsetSmoker\PestRatchets\Exceptions\RatchetException;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel;

/**
 * Adds two options to the Pest command line:
 *
 *   --update-ratchets  write every current offender to each ratchet's baseline
 *   --prune-ratchets   remove stale baseline entries, never adding any
 *
 * The PEST_RATCHETS environment variable (check, update or prune) does the
 * same when the command line is out of reach.
 */
final class Plugin implements HandlesArguments
{
    use HandleArguments;

    public const string ENVIRONMENT_VARIABLE = 'PEST_RATCHETS';

    private const string PARALLEL_GLOBAL = 'PEST_RATCHETS_MODE';

    private static ?Mode $mode = null;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (Parallel::isWorker()) {
            $inherited = Parallel::getGlobal(self::PARALLEL_GLOBAL);

            if (is_string($inherited)) {
                self::$mode = Mode::parse($inherited);
            }

            return $arguments;
        }

        $update = $this->hasArgument('--update-ratchets', $arguments);
        $prune = $this->hasArgument('--prune-ratchets', $arguments);

        if ($update && $prune) {
            throw new RatchetException('Pass --update-ratchets or --prune-ratchets, not both.');
        }

        if (! $update && ! $prune) {
            return $arguments;
        }

        self::$mode = $update ? Mode::Update : Mode::Prune;

        if ($this->hasArgument('--parallel', $arguments) || $this->hasArgument('-p', $arguments)) {
            Parallel::setGlobal(self::PARALLEL_GLOBAL, self::$mode->value);
        }

        return $this->popArgument('--prune-ratchets', $this->popArgument('--update-ratchets', $arguments));
    }

    /**
     * The mode ratchets run in: the command-line option if one was given,
     * else PEST_RATCHETS, else Check.
     */
    public static function mode(): Mode
    {
        if (self::$mode !== null) {
            return self::$mode;
        }

        $environment = getenv(self::ENVIRONMENT_VARIABLE);

        if (! is_string($environment) || trim($environment) === '') {
            return Mode::Check;
        }

        return Mode::parse($environment);
    }

    /**
     * Forgets the mode set by a command-line option. For tests.
     */
    public static function reset(): void
    {
        self::$mode = null;
    }
}
