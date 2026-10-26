<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * A PHP file parsed into an AST whose names are resolved: class names are
 * fully qualified, and `use function` imports are applied to function calls.
 */
final readonly class SourceFile
{
    /**
     * @param  string  $relativePath  path from the ratchet root, with forward slashes
     * @param  list<Node\Stmt>  $ast
     */
    public function __construct(
        public string $relativePath,
        public string $absolutePath,
        public array $ast,
    ) {}

    /**
     * Whether the file sits in one of the given directories (relative to the
     * root, e.g. `config` or `database/migrations`) or is one of those files.
     */
    public function isUnder(string ...$paths): bool
    {
        foreach ($paths as $path) {
            $path = trim(str_replace('\\', '/', $path), '/');

            if ($path === '') {
                return true;
            }

            if ($this->relativePath === $path || str_starts_with($this->relativePath, $path.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T of Node
     *
     * @param  class-string<T>  $type
     * @return list<T>
     */
    public function findInstanceOf(string $type): array
    {
        return array_values((new NodeFinder)->findInstanceOf($this->ast, $type));
    }
}
