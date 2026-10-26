<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets;

use FilesystemIterator;
use OffsetSmoker\PestRatchets\Exceptions\RatchetException;
use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Finds the PHP files under a set of paths and runs a rule over each.
 */
final readonly class Analyzer
{
    private Parser $parser;

    public function __construct(private string $root)
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    /**
     * @param  list<string>  $paths  relative to the root, or absolute inside it
     * @param  list<string>  $excludes  relative to the root, or absolute inside it
     * @return list<Offender> sorted by file, then line, then message
     */
    public function analyse(Rule $rule, array $paths, array $excludes = []): array
    {
        $offenders = [];

        foreach ($this->files($paths, $excludes) as $relative => $absolute) {
            $file = new SourceFile($relative, $absolute, $this->parse($relative, $absolute));

            foreach ($rule->check($file) as $offense) {
                $offenders[] = new Offender($relative, $offense->line, $offense->message);
            }
        }

        usort($offenders, static fn (Offender $a, Offender $b): int => [$a->file, $a->line, $a->message] <=> [$b->file, $b->line, $b->message]);

        return $offenders;
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $excludes
     * @return array<string, string> relative path => absolute path, sorted by relative path
     */
    public function files(array $paths, array $excludes = []): array
    {
        if ($paths === []) {
            throw new RatchetException('A ratchet needs at least one path to scan: call ->in(\'app\') or similar.');
        }

        $excluded = array_map(fn (string $path): string => $this->relativePath($path, 'Excluded path'), $excludes);
        $files = [];

        foreach ($paths as $path) {
            $relativePath = $this->relativePath($path, 'Path');
            $absolutePath = $this->absolute($relativePath);
            $found = [];

            foreach ($this->phpFilesAt($absolutePath, $relativePath) as $relative => $absolute) {
                $found[$relative] = true;

                if (! $this->isExcluded($relative, $excluded)) {
                    $files[$relative] = $absolute;
                }
            }

            if ($found === []) {
                throw new RatchetException("Path '{$relativePath}' holds no .php files, so the ratchet would check nothing.");
            }
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function phpFilesAt(string $absolutePath, string $relativePath): array
    {
        if (is_file($absolutePath)) {
            return str_ends_with($absolutePath, '.php') ? [$relativePath => $absolutePath] : [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
        );

        foreach ($iterator as $info) {
            if (! $info instanceof SplFileInfo || ! $info->isFile() || $info->getExtension() !== 'php') {
                continue;
            }

            $relative = ltrim(substr($info->getPathname(), strlen($absolutePath)), '/');
            $relative = $relativePath === '' ? $relative : $relativePath.'/'.$relative;
            $files[$relative] = $info->getPathname();
        }

        return $files;
    }

    /**
     * @param  list<string>  $excluded
     */
    private function isExcluded(string $relative, array $excluded): bool
    {
        foreach ($excluded as $prefix) {
            if ($prefix === '' || $relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalizes a configured path to one relative to the root, and checks
     * that it exists inside the root.
     */
    private function relativePath(string $path, string $label): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            throw new RatchetException("{$label} must not be empty; use '.' for the root itself.");
        }

        $candidate = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1
            ? $path
            : $this->root.'/'.$path;

        $real = realpath($candidate);

        if ($real === false) {
            throw new RatchetException("{$label} '{$path}' does not exist (looked for {$candidate}).");
        }

        $real = str_replace('\\', '/', $real);

        if ($real === $this->root) {
            return '';
        }

        if (! str_starts_with($real, $this->root.'/')) {
            throw new RatchetException("{$label} '{$path}' is outside the ratchet root {$this->root}.");
        }

        return substr($real, strlen($this->root) + 1);
    }

    private function absolute(string $relative): string
    {
        return $relative === '' ? $this->root : $this->root.'/'.$relative;
    }

    /**
     * @return list<Stmt>
     */
    private function parse(string $relative, string $absolute): array
    {
        $code = file_get_contents($absolute);

        if ($code === false) {
            throw new RatchetException("Could not read {$relative}.");
        }

        try {
            $ast = array_values($this->parser->parse($code) ?? []);
        } catch (Error $error) {
            throw new RatchetException("Could not parse {$relative}: {$error->getMessage()}", previous: $error);
        }

        // NameResolver rewrites names inside the statements; the statements themselves stay.
        (new NodeTraverser(new NameResolver))->traverse($ast);

        return $ast;
    }
}
