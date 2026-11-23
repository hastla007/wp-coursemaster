<?php
/**
 * SEC-045 word list (R0-17 §4.3): claims Course Captain must not make in UI
 * strings, readme.txt and user documentation. German originals plus the
 * English equivalents of the source strings. Used by
 * tools/release/check-zip.php (J-17, on the ZIP) and tests/unit/WordListTest.php
 * (repository: POT, PO, readme.txt, docs/user).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

return array(
	'proctoring'           => '/\bproctor(ing|ed)?\b/i',
	'Online-Aufsicht'      => '/online[- ]?aufsicht/iu',
	'betrugssicher'        => '/betrugssicher/iu',
	'manipulationssicher'  => '/manipulationssicher/iu',
	'fälschungssicher'     => '/f(ä|ae)lschungssicher/iu',
	'rechtssicher'         => '/rechtssicher/iu',
	'DSGVO-konform'        => '/dsgvo[- ]?konform/iu',
	'amtliche Prüfung'     => '/amtliche[n]? pr(ü|ue)fung/iu',
	'cheat-proof'          => '/\b(cheat|fraud)[- ]?proof\b/i',
	'tamper-proof'         => '/\btamper[- ]?proof\b/i',
	'forgery-proof'        => '/\b(forgery|counterfeit)[- ]?proof\b/i',
	'legally compliant'    => '/\blegally (compliant|secure|binding exam)\b/i',
	'GDPR-compliant'       => '/\bgdpr[- ]compliant\b/i',
	'official examination' => '/\bofficial (exam|examination)s?\b/i',
);
