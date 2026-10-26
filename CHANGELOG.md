# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-10-26

### Added

- `ratchet()`, a top-level Pest function that defines a test holding a set
  of paths to a rule and a committed JSON baseline, and `Ratchet` for use
  inside a test or outside Pest's runner.
- Baselines keyed on file and message with a count, so code moving within
  a file does not disturb them. A ratchet fails on new offenders and on
  stale entries, naming the file and line.
- `--update-ratchets` and `--prune-ratchets` Pest options and the
  `PEST_RATCHETS` environment variable, passed on to parallel workers.
  Pruning never adds an entry or raises a count.
- Rules: `NoDirectEnvCalls`, `NoDebugCalls`, `NoInterpolatedRawSql` and
  `ModelsDeclareFillable`, all reading a name-resolved nikic/php-parser AST.
- `Support\Calls` helpers and a guide to writing custom rules.
