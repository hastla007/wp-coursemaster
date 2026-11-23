<?php
/**
 * Synthetische Medien für `cc-fx-rk-v1` (R0-26 §0.2 Nr. 2/3, R0-05 §2.1).
 *
 * Erzeugt Bilder (GD), PDF-Dateien (eigener Minimal-Writer), WebVTT-Untertitel
 * und – wenn `ffmpeg` vorhanden ist – ein kurzes Testvideo. Alles ist
 * selbst erzeugt, rechtefrei (CC0) und ohne Fremdinhalte. Nur für Test-Sites.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tools\Usability;

/**
 * Datei-Erzeugung ohne WordPress-Abhängigkeit (testbar mit reinem PHP).
 */
final class Media {

	/**
	 * Bild als PNG (800 × 450) nach Art.
	 *
	 * @param string $kind matrix|decorative|grid|list|bars|map.
	 * @param string $file Zieldatei.
	 * @return void
	 * @throws \RuntimeException Wenn GD kein Bild anlegen kann.
	 */
	public static function image( string $kind, string $file ): void {
		$w   = 800;
		$h   = 450;
		$img = imagecreatetruecolor( $w, $h );
		if ( false === $img ) {
			throw new \RuntimeException( 'GD kann kein Bild anlegen.' );
		}
		$white = (int) imagecolorallocate( $img, 255, 255, 255 );
		$ink   = (int) imagecolorallocate( $img, 30, 30, 30 );
		$blue  = (int) imagecolorallocate( $img, 34, 113, 177 );
		$light = (int) imagecolorallocate( $img, 220, 232, 245 );
		imagefilledrectangle( $img, 0, 0, $w, $h, $white );
		$text = static function ( int $x, int $y, string $s ) use ( $img, $ink ): void {
			imagestring( $img, 5, $x, $y, self::ascii( $s ), $ink );
		};
		switch ( $kind ) {
			case 'matrix':
				$labels = array( 'wichtig + dringend', 'wichtig, nicht dringend', 'dringend, nicht wichtig', 'weder noch' );
				foreach ( $labels as $i => $label ) {
					$x = 100 + ( $i % 2 ) * 300;
					$y = 50 + intdiv( $i, 2 ) * 180;
					imagefilledrectangle( $img, $x, $y, $x + 290, $y + 170, 0 === $i ? $light : $white );
					imagerectangle( $img, $x, $y, $x + 290, $y + 170, $ink );
					$text( $x + 15, $y + 75, chr( 65 + $i ) . ': ' . $label );
				}
				break;
			case 'grid':
				for ( $c = 0; $c < 4; $c++ ) {
					for ( $r = 0; $r < 4; $r++ ) {
						$x = 150 + $c * 130;
						$y = 80 + $r * 70;
						if ( 2 === $c && 1 === $r ) {
							imagefilledrectangle( $img, $x, $y, $x + 130, $y + 70, $light );
							imagesetthickness( $img, 4 );
							imagerectangle( $img, $x, $y, $x + 130, $y + 70, $blue );
							imagesetthickness( $img, 1 );
						} else {
							imagerectangle( $img, $x, $y, $x + 130, $y + 70, $ink );
						}
					}
					$text( 210 + $c * 130, 55, chr( 65 + $c ) );
				}
				for ( $r = 0; $r < 4; $r++ ) {
					$text( 125, 110 + $r * 70, (string) ( $r + 1 ) );
				}
				break;
			case 'list':
				foreach ( array( 120, 340, 560, 910 ) as $i => $value ) {
					imagerectangle( $img, 250, 70 + $i * 80, 550, 140 + $i * 80, $ink );
					$text( 370, 98 + $i * 80, (string) $value );
				}
				break;
			case 'bars':
				$i = 0;
				foreach ( array(
					'Jan' => 1200,
					'Feb' => 1500,
					'Mar' => 1800,
				) as $month => $value ) {
					$x   = 180 + $i * 170;
					$top = 400 - (int) ( $value / 6 );
					imagefilledrectangle( $img, $x, $top, $x + 110, 400, $blue );
					$text( $x + 35, 410, $month );
					$text( $x + 25, $top - 22, (string) $value );
					++$i;
				}
				imageline( $img, 150, 400, 700, 400, $ink );
				imageline( $img, 150, 40, 150, 400, $ink );
				$text( 260, 15, 'Monatsumsatz in Euro' );
				break;
			case 'map':
				imagefilledrectangle( $img, 250, 40, 550, 120, $light );
				imagerectangle( $img, 250, 40, 550, 120, $blue );
				$text( 270, 70, 'Parkplatz Nord - SAMMELPLATZ' );
				imagefilledrectangle( $img, 300, 160, 500, 300, $white );
				imagerectangle( $img, 300, 160, 500, 300, $ink );
				$text( 335, 222, 'Hauptgebaeude' );
				imagerectangle( $img, 250, 340, 550, 420, $ink );
				$text( 330, 372, 'Parkplatz Sued' );
				break;
			default:
				// Decorative: concentric circles, no information.
				for ( $r = 360; $r > 0; $r -= 60 ) {
					imageellipse( $img, 400, 225, $r, $r, $blue );
				}
		}
		imagepng( $img, $file );
		imagedestroy( $img );
	}

	/**
	 * PDF mit einer Seite Text (PDF 1.4, Helvetica, WinAnsi).
	 *
	 * @param string $title Titel.
	 * @param array  $lines Zeilen.
	 * @phpstan-param list<string> $lines
	 * @param string $file  Zieldatei.
	 * @return void
	 */
	public static function pdf( string $title, array $lines, string $file ): void {
		$y      = 780;
		$stream = 'BT /F1 18 Tf 56 ' . $y . ' Td (' . self::pdf_text( $title ) . ') Tj ET' . "\n";
		foreach ( $lines as $line ) {
			$y      -= 22;
			$stream .= 'BT /F1 11 Tf 56 ' . $y . ' Td (' . self::pdf_text( $line ) . ') Tj ET' . "\n";
		}
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
			'<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . 'endstream',
			'<< /Title (' . self::pdf_text( $title ) . ') /Producer (cc-fx-rk-v1) >>',
		);
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $i => $object ) {
			$offsets[] = strlen( $out );
			$out      .= ( $i + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen( $out );
		$out .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$out .= sprintf( "%010d 00000 n \n", $offset );
		}
		$out .= 'trailer' . "\n<< /Size " . ( count( $objects ) + 1 ) . ' /Root 1 0 R /Info 6 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF\n";
		file_put_contents( $file, $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI tool.
	}

	/**
	 * WebVTT-Untertitel mit gleichmäßig verteilten Zeilen.
	 *
	 * @param array  $cues    Texte.
	 * @phpstan-param list<string> $cues
	 * @param int    $seconds Videolänge.
	 * @param string $file    Zieldatei.
	 * @return void
	 */
	public static function vtt( array $cues, int $seconds, string $file ): void {
		$out  = "WEBVTT\n\n";
		$step = max( 1, intdiv( $seconds, max( 1, count( $cues ) ) ) );
		foreach ( $cues as $i => $cue ) {
			$out .= ( $i + 1 ) . "\n" . self::clock( $i * $step ) . ' --> ' . self::clock( min( $seconds, ( $i + 1 ) * $step ) ) . "\n" . $cue . "\n\n";
		}
		file_put_contents( $file, $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI tool.
	}

	/**
	 * Testvideo (Farbbalken, Sinuston) mit ffmpeg; false ohne ffmpeg.
	 *
	 * @param int    $seconds Länge.
	 * @param string $label   Einblendung.
	 * @param string $file    Zieldatei (.mp4).
	 * @return bool
	 */
	public static function video( int $seconds, string $label, string $file ): bool {
		$ffmpeg = trim( (string) shell_exec( 'command -v ffmpeg 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- CLI tool, fixed command.
		if ( '' === $ffmpeg ) {
			return false;
		}
		$cmd = escapeshellarg( $ffmpeg ) . ' -loglevel error -y -f lavfi -i ' . escapeshellarg( 'testsrc=size=640x360:rate=25:duration=' . $seconds )
			. ' -f lavfi -i ' . escapeshellarg( 'sine=frequency=440:duration=' . $seconds )
			. ' -vf ' . escapeshellarg( "drawtext=text='" . self::ascii( $label ) . "':fontcolor=white:fontsize=28:box=1:boxcolor=black@0.6:x=20:y=20" )
			. ' -c:v libx264 -pix_fmt yuv420p -c:a aac -shortest ' . escapeshellarg( $file ) . ' 2>&1';
		exec( $cmd, $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CLI tool, fixed arguments.
		if ( 0 !== $code || ! is_file( $file ) ) {
			// drawtext needs a font; retry without the label.
			$cmd = escapeshellarg( $ffmpeg ) . ' -loglevel error -y -f lavfi -i ' . escapeshellarg( 'testsrc=size=640x360:rate=25:duration=' . $seconds )
				. ' -f lavfi -i ' . escapeshellarg( 'sine=frequency=440:duration=' . $seconds )
				. ' -c:v libx264 -pix_fmt yuv420p -c:a aac -shortest ' . escapeshellarg( $file ) . ' 2>&1';
			exec( $cmd, $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CLI tool, fixed arguments.
		}
		return 0 === $code && is_file( $file );
	}

	/**
	 * Zeitmarke für WebVTT.
	 *
	 * @param int $seconds Sekunden.
	 * @return string
	 */
	private static function clock( int $seconds ): string {
		return sprintf( '%02d:%02d:%02d.000', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
	}

	/**
	 * Text für PDF-Strings (WinAnsi, Klammern maskiert).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function pdf_text( string $text ): string {
		$converted = function_exists( 'iconv' ) ? iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text ) : false;
		$converted = false === $converted ? self::ascii( $text ) : $converted;
		return strtr(
			$converted,
			array(
				'\\' => '\\\\',
				'('  => '\\(',
				')'  => '\\)',
			)
		);
	}

	/**
	 * ASCII-Umschrift für GD-Bitmap-Schrift und ffmpeg.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function ascii( string $text ): string {
		return strtr(
			$text,
			array(
				'ä' => 'ae',
				'ö' => 'oe',
				'ü' => 'ue',
				'Ä' => 'Ae',
				'Ö' => 'Oe',
				'Ü' => 'Ue',
				'ß' => 'ss',
				'–' => '-',
				'„' => '"',
				'“' => '"',
				"'" => '',
				':' => ' ',
			)
		);
	}
}
