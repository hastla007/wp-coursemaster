# cc-fixture-neutral-v1

Synthetic, rights-free reference fixture (R0-05 §2, clean room E-01/E-02). Built
deterministically by `tests/support/Fixtures/NeutralFixture.php`:

- `NeutralFixture::records( 1|2 )` – package records for PKG-v1 / PKG-v2 (L-A-04 with
  MED-002/MED-003 URLs, L-A-07 with the foreign MED-005 URL);
- `NeutralFixture::package( $version, $file )` – writes a `cc-package` v1 archive;
- `NeutralFixture::load( $dir )` – idempotent loader: users `cc_fx_*` (incl. tutor,
  manager, seats 1–4; U-L4 registered T0 − 40 d), PKG-v1 import, C-C draft with the broken
  M-C1/ER-C, C-D archived, exam rules ER-A/ER-B with variants EP-A-1…3/EP-B-1, media
  MED-001…004 and downloads DL-A-1…3 (upload folder `cc-fixture-neutral-v1`), pages
  PAGE-1/PAGE-2, grants G-1/G-2/G-7/G-10, groups GRP-1/GRP-2, partial access AR-01,
  trainer assignment of U-TUT on C-A;
- on-demand steps (they change what the base users may see, so tests add them inside
  their own transaction): `rule( 'AR-02'…'AR-08', $fx )`, `grant( 'G-3', $fx )`,
  `flashcards( $fx )` (FC-A-01…08, FC-B-01…05 in the optional module LM-FC),
  `bad_media( $dir )` (MED-BAD-1…6);
- `NeutralFixture::uuid( $key )` – deterministic request keys.

MED-002 (`tests/support/Fixtures/media/alpha-04.mp4`) is a committed build artefact of
`tools/fixtures/build-media.sh` (ffmpeg, bit-exact flags); PNG, WebVTT and PDF are
generated at load time.

Users of the fixture: `tests/integration/GoldenMasterTest.php` (GM suite) and the live E2E
smoke `tools/qa/e2e/live-smoke.sh` (fresh WordPress, `php -S`, Playwright/Chromium).
The traceability check `php tools/qa/gm-coverage.php [--matrix]` (also
`tests/unit/GmCoverageTest.php`) maps every GM ID of R0-05 to a test or to the versioned
exception list `tools/qa/gm-coverage-exceptions.php`.

The reference run of the original plugin for calculation cases (E-56, E-328) needs the
separate reviewer role and is not part of this repository.
