# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 0.1.x | Yes |

## Reporting a vulnerability

Please report vulnerabilities privately by email to b@wagner.codes, not in
a public issue. Include the version, a description of the problem and, if
you can, a small reproduction. You will get a reply within a week.

## Scope

The package runs in your test suite. It reads the PHP files under the
paths you configure, refusing any file (a symlink, say) that resolves
outside the ratchet root, and the baseline files you name, and it writes
baseline files only when you run Pest with `--update-ratchets` or
`--prune-ratchets` (or set `PEST_RATCHETS`; `PEST_RATCHETS=update` is
refused when the `CI` environment variable is set). It never executes the code it
analyses, makes no network requests and logs nothing.

Baseline files are validated when read: each entry's file must be a
relative path with forward slashes and no `.` or `..` segments, and
anything else in the file stops the test with an error.
