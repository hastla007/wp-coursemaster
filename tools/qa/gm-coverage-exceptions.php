<?php
/**
 * Versioned exception list of the Golden-Master traceability check (R1-18).
 *
 * Every GM ID of the R0-05 catalogue needs either a test with
 * `@group GM-xxx` or an entry under `exceptions` with a category and a
 * reason. `open` entries are R1 gaps: they are listed so the check passes,
 * but they block R1-GATE until a test exists or the client approves a
 * deviation (E-184, E-55 §5.1). `partial` lists tagged tests that cover the
 * statement only in part (informational, shown in the matrix).
 *
 * Categories: open (R1 gap), deviation (needs §5.1 approval), r2 (E-05 app
 * API), r3 (Pro/commerce), human (manual or separate review role).
 *
 * @package CourseCaptain
 */

return array(
	'exceptions' => array(
		'GM-050' => array( 'open', 'R1-16/R1-20', 'Browser case (offline buffer in localStorage, notice, re-send); the server side is GM-036/GM-037 (ExamTest::test_R120_offline_buffer_and_tabs). Needs a JS exam client E2E.' ),
		'GM-051' => array( 'open', 'R1-16', 'Browser case (abort dialog, single flash message); the server side is GM-040 (ExamTest::test_GM_040_041_cancel).' ),
		'GM-061' => array( 'open', 'R1-28', '409 `ccap_request_in_progress` / `ccap_idempotency_key_reused` exist only for assignments and certificates, not for learning answers; orphaned entries after 5 min untested.' ),
		'GM-077' => array( 'r2', 'E-05', 'Second channel is the app API (no app API in R1).' ),
		'GM-089' => array( 'open', 'R1-13', 'Grant API rules `ccap_access_exists` / `ccap_user_required` do not exist; extend/parallel chains are GM-084.' ),
		'GM-090' => array( 'open', 'R1-13/R1-17', 'Grant audit search by reference untested.' ),
		'GM-092' => array( 'open', 'R1-13', 'Manual + group + free rule at the same time (union of scopes, latest end) untested.' ),
		'GM-093' => array( 'open', 'R1-28', 'No public filter "owns course" in the hook snapshot.' ),
		'GM-104' => array( 'open', 'R1-13/R1-16', 'Return to the invitation after login/registration untested.' ),
		'GM-113' => array( 'open', 'R1-14/R1-40', 'Moving the legacy protected folder with fallback untested.' ),
		'GM-114' => array( 'open', 'R1-14', 'Private folder outside the web root (self test "private", way back) untested.' ),
		'GM-115' => array( 'open', 'R1-14/R1-38', 'HTTP self test of the protected folders (200 critical, 403 green, no answer yellow) untested.' ),
		'GM-117' => array( 'open', 'R1-23', 'Read-only restore check of protected files untested.' ),
		'GM-146' => array( 'open', 'R1-28/R1-38', 'No REST status route; R1-38 provides CLI status and Site Health (deviation candidate for §5.1).' ),
		'GM-147' => array( 'open', 'R1-38', 'No REST health route without version (deviation candidate for §5.1).' ),
		'GM-148' => array( 'open', 'R1-38', 'No anonymous monitor route / cron stall 503 (deviation candidate for §5.1).' ),
		'GM-160' => array( 'r3', 'E-24, R0-12 C-8', 'Signed one-click unsubscribe is Pro (R3-15); N-01 unsubscribe headers are tested in NotificationTest.' ),
		'GM-180' => array( 'deviation', 'R1-31 A-2', 'No `default_access` key in the R0-27 register; access comes from grants and `free` rules (GM-081). Needs the client decision (§5.1).' ),
	),
	'partial'    => array(
		'GM-038' => 'REST save with age_ms; 422 reason `ccap_exam_expired` over REST not asserted.',
		'GM-045' => 'Code `ccap_exam_start_busy` (409) instead of `ccap_busy_retry` (422).',
		'GM-080' => 'Generic schema completeness check (includes `due_at`).',
		'GM-118' => 'Path and state machine on PKG-v2; PKG-v1 carries 71 questions incl. draft Q-A3-011 and 13 flashcards, but no media and blueprints (no media/blueprint import).',
		'GM-125' => 'Chunked steps; no media step (no media import).',
		'GM-128' => 'Upload validator with MED-BAD-1…6; no media import, no legacy quarantine.',
		'GM-136' => 'Scaled to 5 000/1 500 questions (R1-41).',
		'GM-150' => 'Learning answers; HTTP 422 without key not asserted.',
		'GM-152' => 'Trusted proxies; IPv6 /64 bucket not asserted.',
		'GM-154' => 'C-B answers 404 (CT-02 "foreign = invisible") instead of 403.',
		'GM-187' => 'Query constancy; p95 measurement protocol (NFR-018) is a manual run.',
		'GM-197' => 'Free part; Pro/licence/add-on part is R3.',
		'GM-209' => 'Monotonic completion; PUT with an older progress state not asserted.',
	),
);
