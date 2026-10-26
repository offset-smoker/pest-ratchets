<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets\Exceptions;

use RuntimeException;

/**
 * A ratchet that cannot run as configured: a missing path, a baseline that
 * does not parse, a file PHP cannot parse. It is an error, never a pass.
 */
final class RatchetException extends RuntimeException {}
