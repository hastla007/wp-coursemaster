# cc-fixture-neutral-v1

Synthetic, rights-free reference fixture (R0-05 §2). Built deterministically by
`tests/support/Fixtures/NeutralFixture.php`:

- `NeutralFixture::records( 1|2 )` – package records for PKG-v1 / PKG-v2;
- `NeutralFixture::package( $version, $file )` – writes a `cc-package` v1 archive;
- `NeutralFixture::load( $dir )` – idempotent loader (users `cc_fx_*`, PKG-v1 import,
  C-C draft, C-D archived, grants G-1/G-2/G-7, partial access AR-01).

The Golden-Master suite `tests/integration/GoldenMasterTest.php` uses it.
The reference run of the original plugin for calculation cases (E-56, E-328)
needs the separate reviewer role and is not part of this repository.
