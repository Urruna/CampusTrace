# Image Test Fixtures

`tests/security/test_images_multi.php` generates its own temporary fixtures under
`tests/.tmp/`:

- `valid.png` and `valid.jpg`: small images created with GD.
- `renamed-text.png`: plain text accepted by the Baseline extension-only check.
- `plain.txt`: rejected by the extension allow-list.

Generated files and test evidence are ignored by Git. Do not replace these with
real photographs or images containing personal metadata. No manual image set is
required for the automated test.
