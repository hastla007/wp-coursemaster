<?php
/**
 * Package check cases of the import catalogue R0-10 §6.2 that had no
 * tagged test (R0-41 §4.1, LEARN-10): IMP-001…IMP-005, IMP-008…IMP-014.
 * Every manipulated package is otherwise intact (files list, checksums,
 * `content_sha256` and `package_id` recomputed), so the asserted rule is
 * the only finding.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\ImportScreen;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageValidator;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Real tables for the import service; the validator reads archives only.
 *
 * @group LEARN-10
 */
final class ImportCatalogGapsTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Scratch directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Entries of the intact neutral package (path → data), manifest apart.
	 *
	 * @var array<string, string>
	 */
	private array $entries = array();

	/**
	 * Manifest of the intact neutral package.
	 *
	 * @var array<string, mixed>
	 */
	private array $manifest = array();

	/**
	 * Fixture package cc-fixture-neutral-v1 (PKG-v1).
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->dir   = get_temp_dir() . 'ccap-icg-' . wp_generate_password( 6, false, false );
		wp_mkdir_p( $this->dir );
		$zip = new \ZipArchive();
		$zip->open( NeutralFixture::package( 1, $this->dir . '/good.zip' ) );
		$count = $zip->count();
		for ( $i = 0; $i < $count; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			$data = (string) $zip->getFromIndex( $i );
			if ( 'manifest.json' === $name ) {
				$this->manifest = (array) json_decode( $data, true );
			} else {
				$this->entries[ $name ] = $data;
			}
		}
		$zip->close();
	}

	/**
	 * Remove scratch files.
	 */
	public function tear_down(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test cleanup.
		}
		parent::tear_down();
	}

	/**
	 * Build a package: `$listed` entries are listed in the manifest with
	 * correct size and checksum, `$raw` entries are added without listing.
	 *
	 * @param array<string, string>          $listed   Listed entries (path → data).
	 * @param array<string, string>          $raw      Unlisted entries.
	 * @param callable|null                  $mutate   Changes the finished manifest.
	 * @param array<string, string>          $mimes    Declared MIME per path (default: by extension).
	 * @param array<string, array{int, int}> $attributes External attributes per raw path (opsys, attr).
	 * @return string Path.
	 */
	private function pack( array $listed, array $raw = array(), ?callable $mutate = null, array $mimes = array(), array $attributes = array() ): string {
		$manifest          = $this->manifest;
		$manifest['files'] = array();
		ksort( $listed, SORT_STRING );
		foreach ( $listed as $path => $data ) {
			$ext                 = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
			$manifest['files'][] = array(
				'path'      => $path,
				'size'      => strlen( $data ),
				'mime_type' => $mimes[ $path ] ?? ( str_starts_with( $path, 'media/' ) ? ( PackageValidator::MEDIA_TYPES[ $ext ] ?? 'application/octet-stream' ) : 'application/json' ),
				'sha256'    => hash( 'sha256', $data ),
			);
		}
		$manifest['content_sha256'] = PackageValidator::content_sha256( $listed );
		$manifest['package_id']     = (string) preg_replace( '/[0-9a-f]{12}$/', substr( $manifest['content_sha256'], 0, 12 ), (string) $manifest['package_id'] );
		if ( null !== $mutate ) {
			$manifest = $mutate( $manifest );
		}
		$file = $this->dir . '/p-' . wp_generate_password( 8, false, false ) . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $file, \ZipArchive::CREATE );
		if ( null !== $manifest ) {
			$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
		foreach ( $listed + $raw as $path => $data ) {
			$zip->addFromString( $path, $data );
		}
		foreach ( $attributes as $path => $attr ) {
			$zip->setExternalAttributesName( $path, $attr[0], $attr[1] );
		}
		$zip->close();
		return $file;
	}

	/**
	 * Rules found by the validator.
	 *
	 * @param string $file Package.
	 * @return list<string>
	 */
	private static function rules( string $file ): array {
		return array_values( array_unique( array_column( PackageValidator::validate( $file )['errors'], 'rule' ) ) );
	}

	/**
	 * Upload and validate through the import service.
	 *
	 * @param string $file Package.
	 * @return array{status: string, errors: list<array{rule: string, path: string}>, rejected: array<string, string>}
	 */
	private function service_validate( string $file ): array {
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		return $service->validate( $admin, $service->upload( $admin, $file ) );
	}

	/**
	 * IMP-001: an intact package with course, modules, lessons, questions
	 * and an image is `validated`; the dry run plans `create` only.
	 *
	 * @group IMP-001
	 */
	public function test_IMP_001_valid_package(): void {
		$file = $this->pack( $this->entries );
		self::assertSame( array(), self::rules( $file ) );
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		$plan = $service->preview( $admin, $batch )['plan'];
		foreach ( array( 'courses', 'modules', 'lessons', 'questions', 'media' ) as $entity ) {
			self::assertArrayHasKey( $entity, $plan );
			self::assertSame( array( 'create' ), array_keys( $plan[ $entity ] ), $entity );
		}
		$service->discard( $admin, $batch );
	}

	/**
	 * IMP-002…IMP-005: no archive, empty archive, no manifest, foreign
	 * format, major 2, LB package, newer minor version.
	 *
	 * @group IMP-002
	 * @group IMP-003
	 * @group IMP-004
	 * @group IMP-005
	 */
	public function test_IMP_002_005_archive_and_manifest(): void {
		file_put_contents( $this->dir . '/text.zip', 'plain text' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Scratch file.
		self::assertSame( array( 'V1_archive' ), self::rules( $this->dir . '/text.zip' ) );
		// End-of-central-directory record only: a valid but empty ZIP.
		file_put_contents( $this->dir . '/empty.zip', "PK\x05\x06" . str_repeat( "\0", 18 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Scratch file.
		$empty = self::rules( $this->dir . '/empty.zip' );
		self::assertCount( 1, $empty );
		self::assertContains( $empty[0], array( 'V1_entries', 'V1_archive' ) );
		self::assertSame( array( 'V5_manifest_missing' ), self::rules( $this->pack( $this->entries, array(), static fn(): ?array => null ) ) );
		self::assertSame( 'failed', $this->service_validate( $this->pack( $this->entries, array(), static fn(): ?array => null ) )['status'] );

		$set = fn( string $key, $value ): string => $this->pack(
			$this->entries,
			array(),
			static function ( array $m ) use ( $key, $value ): array {
				$m[ $key ] = $value;
				return $m;
			}
		);
		self::assertSame( array( 'V5_format' ), self::rules( $set( 'format', 'other' ) ) );
		self::assertSame( array( 'V5_major' ), self::rules( $set( 'schema_version', '2.0.0' ) ) );
		self::assertSame( array( 'V5_minor' ), self::rules( $set( 'schema_version', '1.9.0' ) ), 'IMP-005: newer minor than the plugin' );
		self::assertSame( array(), self::rules( $set( 'schema_version', '1.0.7' ) ), 'patch versions are accepted' );

		// LB package: no `format`, LB package ID (E-03: the core never reads it; hint see test_IMP_004_005_hints_in_import_detail).
		$lb = $this->pack(
			$this->entries,
			array(),
			static function ( array $m ): array {
				unset( $m['format'] );
				$m['package_id'] = 'boatingschool-abc1234-' . substr( (string) $m['content_sha256'], 0, 12 );
				return $m;
			}
		);
		self::assertContains( 'V5_format', self::rules( $lb ) );
		self::assertSame( 'failed', $this->service_validate( $lb )['status'] );
	}

	/**
	 * IMP-004/IMP-005 in the import detail: readable hints that only name
	 * what exists (course export, real schema versions), the rule code in
	 * brackets; no migration tool, no plugin update.
	 *
	 * @group IMP-004
	 * @group IMP-005
	 */
	public function test_IMP_004_005_hints_in_import_detail(): void {
		$set     = fn( string $key, $value ): string => $this->pack(
			$this->entries,
			array(),
			static function ( array $m ) use ( $key, $value ): array {
				$m[ $key ] = $value;
				return $m;
			}
		);
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$highest = '1.' . PackageValidator::SCHEMA_MINOR . '.x';

		$newer = $service->upload( $admin, $set( 'schema_version', '1.9.0' ) );
		self::assertSame( 'failed', $service->validate( $admin, $newer )['status'] );
		$page = ImportScreen::html( array( 'batch' => $newer ), $this->admin );
		self::assertStringContainsString( 'The package has schema version 1.9.0, but this site imports only schema versions 1.0.0 to ' . $highest . '. Export the package again with a matching version and upload that file. (rule: V5_minor)', $page );

		$foreign = $service->upload( $admin, $set( 'format', 'other' ) );
		self::assertSame( 'failed', $service->validate( $admin, $foreign )['status'] );
		$page = html_entity_decode( ImportScreen::html( array( 'batch' => $foreign ), $this->admin ), ENT_QUOTES );
		self::assertStringContainsString( 'This file is not a Course Captain package. Only packages created under "Import content > Course export" can be imported', $page );
		self::assertStringContainsString( '(rule: V5_format)', $page );
		self::assertDoesNotMatchRegularExpression( '/migration tool|update the plugin/i', $page );
	}

	/**
	 * IMP-008: more than 20 000 entries.
	 *
	 * @group IMP-008
	 */
	public function test_IMP_008_entry_limit(): void {
		$raw = array();
		for ( $i = 0; $i <= PackageValidator::MAX_ENTRIES; $i++ ) {
			$raw[ sprintf( 'media/f%05d.png', $i ) ] = '';
		}
		self::assertSame( array( 'V1_entries' ), self::rules( $this->pack( array(), $raw ) ) );
	}

	/**
	 * IMP-009…IMP-011: paths outside the package, hidden files, symlinks
	 * and unexpected files are rejected.
	 *
	 * @group IMP-009
	 * @group IMP-010
	 * @group IMP-011
	 */
	public function test_IMP_009_011_paths_symlinks_unexpected_files(): void {
		foreach ( array( '../x', '/etc/x', 'C:\\x', 'data\\..\\x', 'media/.hidden', 'media/./a.png' ) as $path ) {
			self::assertSame( array( 'V3_path' ), self::rules( $this->pack( $this->entries, array( $path => 'x' ) ) ), $path );
		}
		foreach ( array( 'evil.php', 'data/x.txt', 'data/questions/1.json' ) as $path ) {
			self::assertSame( array( 'V3_entry' ), self::rules( $this->pack( $this->entries, array( $path => 'x' ) ) ), $path );
		}
		$link = $this->pack( $this->entries, array( 'media/link.png' => '/etc/passwd' ), null, array(), array( 'media/link.png' => array( \ZipArchive::OPSYS_UNIX, 0120777 << 16 ) ) );
		self::assertContains( 'V3_symlink', self::rules( $link ) );
		self::assertSame( 'failed', $this->service_validate( $link )['status'] );
		// Junk files of archivers are skipped silently (R0-10 §1.1).
		self::assertSame(
			array(),
			self::rules(
				$this->pack(
					$this->entries,
					array(
						'__MACOSX/._x'    => 'x',
						'media/.DS_Store' => 'x',
					)
				)
			)
		);
	}

	/**
	 * IMP-012/IMP-013: dangerous media names and media whose content or
	 * declared type does not match; the import rejects the file and keeps
	 * the package (object error of the medium, R0-38 §2.3).
	 *
	 * @group IMP-012
	 * @group IMP-013
	 */
	public function test_IMP_012_013_media_rules(): void {
		$png = NeutralFixture::media_files()['a1-003.png'];
		foreach ( array(
			'media/a.php.png' => array( $png, 'V4_dangerous' ),
			'media/a.svg'     => array( '<svg xmlns="http://www.w3.org/2000/svg"/>', 'V4_dangerous' ),
			'media/a.html'    => array( '<p>x</p>', 'V4_dangerous' ),
			'media/a.exe.pdf' => array( '%PDF-1.4', 'V4_dangerous' ),
			'media/b.png'     => array( "<?php echo 'x';\n", 'V4_mime_detected' ),
		) as $path => $case ) {
			$file = $this->pack( $this->entries + array( $path => $case[0] ) );
			self::assertSame( array( $case[1] ), self::rules( $file ), $path );
			$result = $this->service_validate( $file );
			self::assertSame( 'validated', $result['status'], $path );
			self::assertSame( array( $path => $case[1] ), $result['rejected'], $path );
		}
		self::assertSame( array( 'V4_mime_declared' ), self::rules( $this->pack( $this->entries + array( 'media/c.png' => $png ), array(), null, array( 'media/c.png' => 'image/jpeg' ) ) ) );
		self::assertSame( array(), self::rules( $this->pack( $this->entries + array( 'media/d.png' => $png ) ) ), 'counter-check: a real PNG' );
	}

	/**
	 * IMP-014: wrong checksum or size, unlisted file, wrong `content_sha256`.
	 *
	 * @group IMP-014
	 */
	public function test_IMP_014_integrity(): void {
		$first = (string) array_key_first( $this->entries );
		$bad   = $this->pack(
			$this->entries,
			array(),
			static function ( array $m ): array {
				$m['files'][0]['sha256'] = str_repeat( '0', 64 );
				return $m;
			}
		);
		self::assertSame( array( 'V5_checksum' ), self::rules( $bad ) );
		$size = $this->pack(
			$this->entries,
			array(),
			static function ( array $m ): array {
				++$m['files'][0]['size'];
				return $m;
			}
		);
		self::assertSame( array( 'V5_checksum' ), self::rules( $size ) );
		self::assertSame( array( 'V5_unlisted' ), self::rules( $this->pack( $this->entries, array( 'data/questions/000099.json' => '[]' ) ) ) );
		$content = $this->pack(
			$this->entries,
			array(),
			static function ( array $m ): array {
				$m['content_sha256'] = str_repeat( 'a', 64 );
				return $m;
			}
		);
		self::assertContains( 'V5_content_sha256', self::rules( $content ) );
		$missing = $this->entries;
		unset( $missing[ $first ] );
		$gone = $this->pack(
			$missing,
			array(),
			function ( array $m ) use ( $first ): array {
				$m['files'][] = array(
					'path'      => $first,
					'size'      => strlen( $this->entries[ $first ] ),
					'mime_type' => 'application/json',
					'sha256'    => hash( 'sha256', $this->entries[ $first ] ),
				);
				return $m;
			}
		);
		self::assertContains( 'V5_file_missing', self::rules( $gone ) );
		foreach ( array( $bad, $size, $content, $gone ) as $file ) {
			self::assertSame( 'failed', $this->service_validate( $file )['status'] );
		}
	}
}
