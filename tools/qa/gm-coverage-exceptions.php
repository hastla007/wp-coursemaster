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
		'GM-077' => array( 'r2', 'E-05', 'Second channel is the app API (no app API in R1).' ),
		'GM-160' => array( 'r3', 'E-24, R0-12 C-8', 'Signed one-click unsubscribe is Pro (R3-15); N-01 unsubscribe headers are tested in NotificationTest.' ),
		'GM-180' => array( 'deviation', 'R1-31 A-2', 'No `default_access` key in the R0-27 register; access comes from grants and `free` rules (GM-081). Approved by the client as a deviation (E-334, 23.11.2026).' ),
	),
	'partial'    => array(
		'GM-045' => 'Code `ccap_exam_start_busy` (409) instead of `ccap_busy_retry` (422).',
		'GM-118' => 'Path and state machine on PKG-v2; PKG-v1 carries 71 questions incl. draft Q-A3-011 and 13 flashcards, but no media and blueprints (no media/blueprint import).',
		'GM-125' => 'Chunked steps; no media step (no media import).',
		'GM-128' => 'Upload validator with MED-BAD-1…6; no media import, no legacy quarantine.',
		'GM-136' => 'Scaled: 600 questions with 3 defective, abort at 50 % and resume (test_GM_136), 5 000/1 500 questions (BIG-001/BIG-020) instead of PKG-LARGE with 20 000; the full volume is a load run on R-M (R1-41/R1-22 A-1).',
		'GM-154' => 'C-B answers 404 (CT-02 "foreign = invisible") instead of 403.',
		'GM-187' => 'Query constancy and ceilings run in CI; the p95 time budgets (R0-22/NFR-018, C-A with 200 learners) need the manual load run on R-M (R1-22 A-1).',
		'GM-197' => 'Free part complete (no explanation in learning/exam, text stored and exported, solutions per E-147); delivery with Pro, after licence end (E-268) and with the add-on off (E-269) belongs to the Pro add-on (R0-39 §2.5, FG-08/G-12, R3-08).',
	),
);
