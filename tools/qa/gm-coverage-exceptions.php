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
		'GM-006' => array( 'open', 'R1-25', 'No test for "≤ 2 queries for all courses and modules" and reload after invalidate(); the cache layer itself is covered by CacheTest (K1/K2).' ),
		'GM-010' => array( 'open', 'R1-07/R1-10', 'Finding: answers of a lesson quiz do not feed the QuizProvider progress (`answered`), so a lesson is not completed by its quiz; only the completion rule is unit-tested (ActivityUnitTest).' ),
		'GM-050' => array( 'open', 'R1-16/R1-20', 'Browser case (offline buffer in localStorage, notice, re-send); the server side is GM-036/GM-037 (ExamTest::test_R120_offline_buffer_and_tabs). Needs a JS exam client E2E.' ),
		'GM-051' => array( 'open', 'R1-16', 'Browser case (abort dialog, single flash message); the server side is GM-040 (ExamTest::test_GM_040_041_cancel).' ),
		'GM-061' => array( 'open', 'R1-28', '409 `ccap_request_in_progress` / `ccap_idempotency_key_reused` exist only for assignments and certificates, not for learning answers; orphaned entries after 5 min untested.' ),
		'GM-064' => array( 'open', 'R1-15/R1-41', 'No flashcard import (the runner imports courses, modules, lessons, questions), so a card removed from a package cannot become `source_archived`.' ),
		'GM-068' => array( 'open', 'R1-12/R1-40', 'Legacy rows without `due_at` (mastered stays mastered, due = last + 7 d) untested.' ),
		'GM-074' => array( 'open', 'R1-12', 'Targeted practice of one question with 422/404/403 variants untested.' ),
		'GM-075' => array( 'open', 'R1-10', 'Stage counts summing up to the total (secure/due/completed) untested.' ),
		'GM-077' => array( 'r2', 'E-05', 'Second channel is the app API (no app API in R1).' ),
		'GM-078' => array( 'open', 'R1-10', 'Course progress reset with kept exam history and audit untested.' ),
		'GM-079' => array( 'open', 'R1-10/R1-03', 'Progress report per course for authorised roles (U-TUT only C-A) untested.' ),
		'GM-089' => array( 'open', 'R1-13', 'Grant API rules `ccap_access_exists` / `ccap_user_required` do not exist; extend/parallel chains are GM-084.' ),
		'GM-090' => array( 'open', 'R1-13/R1-17', 'Grant audit search by reference untested.' ),
		'GM-092' => array( 'open', 'R1-13', 'Manual + group + free rule at the same time (union of scopes, latest end) untested.' ),
		'GM-093' => array( 'open', 'R1-28', 'No public filter "owns course" in the hook snapshot.' ),
		'GM-104' => array( 'open', 'R1-13/R1-16', 'Return to the invitation after login/registration untested.' ),
		'GM-113' => array( 'open', 'R1-14/R1-40', 'Moving the legacy protected folder with fallback untested.' ),
		'GM-114' => array( 'open', 'R1-14', 'Private folder outside the web root (self test "private", way back) untested.' ),
		'GM-115' => array( 'open', 'R1-14/R1-38', 'HTTP self test of the protected folders (200 critical, 403 green, no answer yellow) untested.' ),
		'GM-117' => array( 'open', 'R1-23', 'Read-only restore check of protected files untested.' ),
		'GM-123' => array( 'open', 'R1-15', 'Rights override with reason and audit untested.' ),
		'GM-127' => array( 'open', 'R1-15/R1-05', 'Bulk rights release with confirmation untested.' ),
		'GM-129' => array( 'open', 'R1-15', 'Upload limit = smallest value and system files (PKG-SYS) untested.' ),
		'GM-130' => array( 'open', 'R1-15', 'Link import (https, ≤ 3 redirects, SHA-256) not implemented.' ),
		'GM-135' => array( 'open', 'R1-15', 'JSON schema subset validator unit cases ($ref over files, if/then, contains) untested.' ),
		'GM-137' => array( 'open', 'R1-05/R1-26', 'Hand-built C-A with the structure hash of the imported C-A untested.' ),
		'GM-146' => array( 'open', 'R1-28/R1-38', 'No REST status route; R1-38 provides CLI status and Site Health (deviation candidate for §5.1).' ),
		'GM-147' => array( 'open', 'R1-38', 'No REST health route without version (deviation candidate for §5.1).' ),
		'GM-148' => array( 'open', 'R1-38', 'No anonymous monitor route / cron stall 503 (deviation candidate for §5.1).' ),
		'GM-160' => array( 'r3', 'E-24, R0-12 C-8', 'Signed one-click unsubscribe is Pro (R3-15); N-01 unsubscribe headers are tested in NotificationTest.' ),
		'GM-162' => array( 'open', 'R1-17', 'Audit retention in batches (5 000 then 1 000, young 10 stay) untested.' ),
		'GM-167' => array( 'open', 'R1-40', 'Exactly one masked admin notice for migration errors untested.' ),
		'GM-174' => array( 'open', 'R1-17', 'Self-service export/erasure requests (GET never erases) untested.' ),
		'GM-175' => array( 'open', 'R1-17', 'Asynchronous export (pending → ready, 409, owner only, expiry) untested.' ),
		'GM-176' => array( 'open', 'R1-17/R1-34', 'Privacy policy text (section, periods, 0 external links) untested.' ),
		'GM-180' => array( 'deviation', 'R1-31 A-2', 'No `default_access` key in the R0-27 register; access comes from grants and `free` rules (GM-081). Needs the client decision (§5.1).' ),
		'GM-189' => array( 'open', 'R1-24 (E-16)', 'Starter content/FAQ seeder mechanics not implemented.' ),
		'GM-190' => array( 'open', 'R1-24 (E-16)', 'FAQ cache/REST/gate block not implemented.' ),
	),
	'partial'    => array(
		'GM-038' => 'REST save with age_ms; 422 reason `ccap_exam_expired` over REST not asserted.',
		'GM-045' => 'Code `ccap_exam_start_busy` (409) instead of `ccap_busy_retry` (422).',
		'GM-080' => 'Generic schema completeness check (includes `due_at`).',
		'GM-118' => 'Path and state machine on PKG-v2; manifest counts differ from R0-05 (no media/blueprint/flashcard import, no draft Q-A3-011 in PKG-v1).',
		'GM-120' => 'Question part only; card archive (FC-A-08) needs flashcard import (GM-064).',
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
