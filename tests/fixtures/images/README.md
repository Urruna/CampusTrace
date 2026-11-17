# Image Test Fixtures

`tests/security/test_images_multi.php` generates its own temporary fixtures under
`tests/.tmp/`:

- `valid.png` and `valid.jpg`: small images created with GD.
- `renamed-text.png`: plain text accepted by the Baseline extension-only check.
- `plain.txt`: rejected by the extension allow-list.

Generated automated fixtures and test evidence are ignored by Git. Do not replace
the temporary automated fixtures with real photographs or images containing
personal metadata. No manual image set is required for the automated test.

The tracked [manual](./manual/README.md) directory contains authorized,
non-sensitive course fixtures for browser-based tests, including normal
multi-image, R01 and R02 examples.
