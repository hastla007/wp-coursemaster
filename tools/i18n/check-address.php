<?php
/**
 * SET-CC-14: form of address in the German translations.
 *
 * `course-captain-de_DE.po` uses the informal "du" throughout,
 * `course-captain-de_DE_formal.po` the formal "Sie". The check flags formal
 * forms in the informal file and informal forms in the formal file, using a
 * word list:
 *
 * - formal: "Sie", "Ihr", "Ihre" … capitalised inside a sentence (at the start
 *   of a sentence they can mean "they"/"their"), "Ihnen" anywhere;
 * - informal: "du", "dich", "dir", "dein…", "euch", "euer…", the second person
 *   singular of common verbs ("kannst", "hast" …) and imperatives at the start
 *   of a sentence ("Gib", "Wähle", "Klicke" …).
 *
 * Usage:
 *   php8.5 tools/i18n/check-address.php
 *   php8.5 tools/i18n/check-address.php --informal=<po> --formal=<po>
 *
 * Exit 0 = no findings, 1 = findings, 2 = usage error.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- CLI tool without WordPress.

/**
 * Translations of a PO file (header excluded).
 *
 * @param string $path File.
 * @return list<array{id: string, str: string}>
 */
function ccap_address_po_entries( string $path ): array {
	$lines   = (array) file( $path, FILE_IGNORE_NEW_LINES );
	$entries = array();
	$id      = null;
	$strs    = array();
	$field   = '';
	$flush   = static function () use ( &$entries, &$id, &$strs ): void {
		if ( null !== $id && '' !== $id ) {
			foreach ( $strs as $str ) {
				$entries[] = array(
					'id'  => $id,
					'str' => $str,
				);
			}
		}
		$id   = null;
		$strs = array();
	};
	$unquote = static fn( string $s ): string => stripcslashes( $s );
	foreach ( $lines as $line ) {
		$line = (string) $line;
		if ( '' === trim( $line ) ) {
			$flush();
			continue;
		}
		if ( str_starts_with( $line, '#' ) ) {
			continue;
		}
		if ( 1 === preg_match( '/^msgid "(.*)"$/', $line, $m ) ) {
			$flush();
			$id    = $unquote( $m[1] );
			$field = 'id';
		} elseif ( 1 === preg_match( '/^msgstr(?:\[(\d+)\])? "(.*)"$/', $line, $m ) ) {
			$field          = 's' . $m[1];
			$strs[ $field ] = $unquote( $m[2] );
		} elseif ( 1 === preg_match( '/^(msgctxt|msgid_plural) "(.*)"$/', $line ) ) {
			$field = 'other';
		} elseif ( 1 === preg_match( '/^"(.*)"$/', $line, $m ) ) {
			if ( 'id' === $field ) {
				$id .= $unquote( $m[1] );
			} elseif ( isset( $strs[ $field ] ) ) {
				$strs[ $field ] .= $unquote( $m[1] );
			}
		}
	}
	$flush();
	return $entries;
}

/**
 * Formal forms in a text that should use "du".
 *
 * @param string $text Translation.
 * @return list<string>
 */
function ccap_address_formal_hits( string $text ): array {
	$hits = array();
	// "Ihnen" is formal wherever it stands; lower-case "ihnen" means "them".
	if ( preg_match_all( '/\bIhnen\b/u', $text, $m ) ) {
		$hits = array_merge( $hits, $m[0] );
	}
	// Capitalised inside a sentence: preceded by a word (not by sentence punctuation or an opening quote).
	if ( preg_match_all( '/(?<=[\p{L}\p{N},)\]%}])\s+(Sie|Ihr|Ihre|Ihren|Ihrem|Ihrer|Ihres)\b/u', $text, $m ) ) {
		$hits = array_merge( $hits, $m[1] );
	}
	return $hits;
}

/**
 * Informal forms in a text that should use "Sie".
 *
 * @param string $text Translation.
 * @return list<string>
 */
function ccap_address_informal_hits( string $text ): array {
	$hits     = array();
	$pronouns = '/\b(du|dich|dir|dein|deine|deinen|deinem|deiner|deines|euch|euer|eure|euren|eurem|eurer|eures)\b/iu';
	$verbs    = '/\b(kannst|darfst|hast|bist|musst|willst|möchtest|wirst|weißt|siehst|erhältst|solltest|könntest|wusstest|wurdest)\b/iu';
	$imperat  = '/(?:^|[.!?:;]\s+|[„“"(–]\s*)(Gib|Wähle|Prüfe|Klicke|Klick|Lade|Füge|Setze|Bestätige|Melde|Öffne|Trage|Korrigiere|Schalte|Speichere|Entferne|Aktiviere|Deaktiviere|Beachte|Nutze|Verwende|Ändere|Installiere|Markiere|Richte|Lege|Erstelle|Behebe|Kläre|Sende|Warte|Starte|Führe|Exportiere|Importiere|Veröffentliche|Wende|Verwirf|Nimm|Sieh|Lies|Schreib|Schreibe|Beschreib|Tu|Frag|Ergänze|Verringere|Fülle|Kreuze|Ordne|Beantworte|Versuche es)\b(?!\s+Sie\b)/u';
	foreach ( array( $pronouns, $verbs ) as $pattern ) {
		if ( preg_match_all( $pattern, $text, $m ) ) {
			$hits = array_merge( $hits, $m[1] );
		}
	}
	if ( preg_match_all( $imperat, $text, $m ) ) {
		$hits = array_merge( $hits, $m[1] );
	}
	return $hits;
}

/**
 * Findings for one file.
 *
 * @param string $path   PO file.
 * @param string $expect `informal` (flag formal forms) or `formal` (flag informal forms).
 * @return array{entries: int, findings: list<string>}
 */
function ccap_address_check_file( string $path, string $expect ): array {
	$entries  = ccap_address_po_entries( $path );
	$findings = array();
	foreach ( $entries as $entry ) {
		$hits = 'informal' === $expect ? ccap_address_formal_hits( $entry['str'] ) : ccap_address_informal_hits( $entry['str'] );
		if ( array() !== $hits ) {
			$findings[] = sprintf( '%s: [%s] %s', basename( $path ), implode( ', ', array_unique( $hits ) ), $entry['str'] );
		}
	}
	return array(
		'entries'  => count( $entries ),
		'findings' => $findings,
	);
}

if ( realpath( (string) ( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) === __FILE__ ) {
	$root  = dirname( __DIR__, 2 );
	$files = array(
		'informal' => $root . '/languages/course-captain-de_DE.po',
		'formal'   => $root . '/languages/course-captain-de_DE_formal.po',
	);
	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( 1 === preg_match( '/^--(informal|formal)=(.+)$/', $arg, $m ) ) {
			$files[ $m[1] ] = $m[2];
		} else {
			fwrite( STDERR, "Usage: php tools/i18n/check-address.php [--informal=<po>] [--formal=<po>]\n" );
			exit( 2 );
		}
	}
	$total = 0;
	foreach ( $files as $expect => $path ) {
		if ( ! is_file( $path ) ) {
			fwrite( STDERR, "Missing file: $path\n" );
			exit( 2 );
		}
		$result = ccap_address_check_file( $path, $expect );
		foreach ( $result['findings'] as $finding ) {
			echo $finding . "\n";
		}
		printf( "%s (%s): %d translations, %d findings\n", basename( $path ), 'informal' === $expect ? 'du' : 'Sie', $result['entries'], count( $result['findings'] ) );
		$total += count( $result['findings'] );
	}
	echo ( 0 === $total ? 'check-address: OK' : 'check-address: ' . $total . ' finding(s)' ) . "\n";
	exit( 0 === $total ? 0 : 1 );
}
