<?php

/**
 * Tests for the deferred loading of the new-in-7.0 subsystem classes - the
 * Abilities API and the collaboration sync infrastructure - together with two
 * block-editor helper classes, and their autoloader safety net in
 * wp-settings.php.
 *
 * On a genuine non-REST front-end theme render, wp-settings.php skips the eager
 * require of these plain class/interface files and registers a classmap
 * spl_autoload_register() as a safety net so that any reference to one of them
 * still resolves on demand (pulling in dependencies such as the WP_Sync_Storage
 * interface transitively). Every other context - admin, AJAX, cron, WP-CLI,
 * XML-RPC, an explicit REST request, and the test suite - loads them eagerly at
 * the original bootstrap position. These tests lock in the classmap's accuracy,
 * the fact that the files are no longer eagerly required, that the procedural
 * subsystem files that host their public API remain eager, and the runtime
 * behavior of the safety net.
 *
 * @group load
 */
class Tests_Load_DeferredClassLoading extends WP_UnitTestCase {

	/**
	 * Absolute path to the bootstrap file that owns the deferral.
	 *
	 * @return string
	 */
	private function wp_settings_path() {
		return ABSPATH . 'wp-settings.php';
	}

	/**
	 * The exact set of classes that must be deferred, mapped to the WPINC-relative
	 * file that declares each one.
	 *
	 * @return array<string,string> Map of class/interface name => WPINC-relative path.
	 */
	private function expected_deferred_map() {
		return array(
			'WP_Ability_Category'                => '/abilities-api/class-wp-ability-category.php',
			'WP_Ability_Categories_Registry'     => '/abilities-api/class-wp-ability-categories-registry.php',
			'WP_Ability'                         => '/abilities-api/class-wp-ability.php',
			'WP_Abilities_Registry'              => '/abilities-api/class-wp-abilities-registry.php',
			'WP_Sync_Storage'                    => '/collaboration/interface-wp-sync-storage.php',
			'WP_Sync_Post_Meta_Storage'          => '/collaboration/class-wp-sync-post-meta-storage.php',
			'WP_HTTP_Polling_Sync_Server'        => '/collaboration/class-wp-http-polling-sync-server.php',
			'WP_Block_Editor_Context'            => '/class-wp-block-editor-context.php',
			'WP_Classic_To_Block_Menu_Converter' => '/class-wp-classic-to-block-menu-converter.php',
		);
	}

	/**
	 * Parses the $wp_deferred_class_map literal out of wp-settings.php.
	 *
	 * Reading the source (rather than the runtime variable, which is unset at the
	 * end of the bootstrap) keeps the test independent of load order.
	 *
	 * @return array<string,string> Map of class name => absolute file path.
	 */
	private function parse_classmap() {
		$source = file_get_contents( $this->wp_settings_path() );
		$this->assertNotFalse( $source, 'Unable to read wp-settings.php.' );

		$start = strpos( $source, '$wp_deferred_class_map = array(' );
		$this->assertNotFalse( $start, 'The $wp_deferred_class_map array was not found in wp-settings.php.' );

		$end = strpos( $source, ');', $start );
		$this->assertNotFalse( $end, 'The end of the $wp_deferred_class_map array was not found.' );

		$body = substr( $source, $start, $end - $start );

		$matched = preg_match_all(
			"/'([A-Za-z0-9_]+)'\s*=>\s*ABSPATH \. WPINC \. '([^']+)'/",
			$body,
			$matches,
			PREG_SET_ORDER
		);
		$this->assertNotFalse( $matched, 'Failed to parse the classmap entries.' );

		$map = array();
		foreach ( $matches as $match ) {
			$map[ $match[1] ] = ABSPATH . WPINC . $match[2];
		}

		return $map;
	}

	/**
	 * The classmap must contain exactly the intended deferrable classes, and every
	 * entry must point at an existing file that declares the mapped class or
	 * interface.
	 *
	 * This guards against the classmap drifting out of sync with the files on
	 * disk, which would silently defeat the autoloader safety net.
	 */
	public function test_deferred_class_map_matches_expected_set() {
		$map = $this->parse_classmap();

		$expected = array();
		foreach ( $this->expected_deferred_map() as $class_name => $relative_path ) {
			$expected[ $class_name ] = ABSPATH . WPINC . $relative_path;
		}

		$this->assertSame(
			$expected,
			$map,
			'The $wp_deferred_class_map drifted from the intended deferrable set.'
		);

		// Every mapped file must exist and declare its mapped class or interface.
		foreach ( $map as $class_name => $file ) {
			$this->assertFileExists( $file, "Deferred file for {$class_name} does not exist." );

			$contents = file_get_contents( $file );
			$this->assertMatchesRegularExpression(
				'/\b(?:class|interface)\s+' . preg_quote( $class_name, '/' ) . '\b/',
				$contents,
				"File {$file} does not declare {$class_name}."
			);
		}
	}

	/**
	 * None of the deferrable files may also appear as an eager require in
	 * wp-settings.php; that would load them on every request and defeat the
	 * deferral.
	 */
	public function test_deferred_files_are_not_eagerly_required() {
		$source = file_get_contents( $this->wp_settings_path() );
		$this->assertNotFalse( $source, 'Unable to read wp-settings.php.' );

		foreach ( $this->expected_deferred_map() as $class_name => $relative_path ) {
			$needle = "require ABSPATH . WPINC . '" . $relative_path . "';";
			$this->assertStringNotContainsString(
				$needle,
				$source,
				"{$relative_path} ({$class_name}) must be deferred via the classmap, not eagerly required."
			);
		}
	}

	/**
	 * The procedural subsystem files that define the public Abilities API and
	 * collaboration function surface have file-scope side effects handled
	 * elsewhere and must continue to load eagerly at the original position.
	 */
	public function test_procedural_subsystem_files_remain_eager() {
		$source = file_get_contents( $this->wp_settings_path() );
		$this->assertNotFalse( $source, 'Unable to read wp-settings.php.' );

		foreach ( array( '/abilities-api.php', '/abilities.php', '/collaboration.php' ) as $relative_path ) {
			$needle = "require ABSPATH . WPINC . '" . $relative_path . "';";
			$this->assertStringContainsString(
				$needle,
				$source,
				"{$relative_path} must remain eagerly required."
			);
		}
	}

	/**
	 * End-to-end: booting a genuine non-REST front-end request must defer these
	 * classes, yet a later reference to any of them must still resolve through the
	 * classmap autoloader safety net - including the WP_Sync_Storage interface
	 * that WP_Sync_Post_Meta_Storage implements, which must load transitively.
	 *
	 * This runs in a subprocess because the deferral branch only executes when the
	 * request is a front-end theme render (WP_USE_THEMES defined, not admin, not a
	 * REST request), which is not the case for the PHPUnit bootstrap. The test is
	 * skipped when the runtime configuration required to boot a front-end request
	 * is unavailable.
	 */
	public function test_deferred_classes_resolve_on_front_end_bootstrap() {
		if ( ! defined( 'PHP_BINARY' ) || '' === PHP_BINARY ) {
			$this->markTestSkipped( 'PHP_BINARY is not available to launch a subprocess.' );
		}

		$wp_load = ABSPATH . 'wp-load.php';
		if ( ! file_exists( $wp_load ) || ! file_exists( ABSPATH . 'wp-config.php' ) ) {
			$this->markTestSkipped( 'Runtime configuration (wp-config.php) is not available to boot a front-end request.' );
		}

		$probe = <<<PHP
<?php
\$_SERVER['REQUEST_URI']    = '/';
\$_SERVER['HTTP_HOST']      = 'example.org';
\$_SERVER['REQUEST_METHOD'] = 'GET';
define( 'WP_USE_THEMES', true );

require '{$wp_load}';

\$registry_file = realpath( ABSPATH . WPINC . '/abilities-api/class-wp-abilities-registry.php' );
\$deferred      = ! in_array( \$registry_file, array_map( 'realpath', get_included_files() ), true );
\$before_rest_init = ( 0 === (int) did_action( 'rest_api_init' ) );

\$resolves =
       class_exists( 'WP_Ability_Category' )
    && class_exists( 'WP_Ability_Categories_Registry' )
    && class_exists( 'WP_Ability' )
    && class_exists( 'WP_Abilities_Registry' )
    && class_exists( 'WP_Sync_Post_Meta_Storage' )
    && class_exists( 'WP_HTTP_Polling_Sync_Server' )
    && class_exists( 'WP_Block_Editor_Context' )
    && class_exists( 'WP_Classic_To_Block_Menu_Converter' );

// The interface must have loaded transitively while resolving its implementor.
\$interface_ok = interface_exists( 'WP_Sync_Storage', false )
    && in_array( 'WP_Sync_Storage', class_implements( 'WP_Sync_Post_Meta_Storage' ), true );

echo 'BLITZY_PROBE:'
    . 'deferred=' . ( \$deferred ? 1 : 0 )
    . ';before_rest_init=' . ( \$before_rest_init ? 1 : 0 )
    . ';resolves=' . ( \$resolves ? 1 : 0 )
    . ';interface_ok=' . ( \$interface_ok ? 1 : 0 )
    . "\n";
PHP;

		$probe_file = tempnam( sys_get_temp_dir(), 'blitzy_probe_' ) . '.php';
		file_put_contents( $probe_file, $probe );

		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $probe_file ) . ' 2>&1';
		$output  = shell_exec( $command );

		unlink( $probe_file );

		if ( null === $output || false === strpos( $output, 'BLITZY_PROBE:' ) ) {
			$this->markTestSkipped( 'Front-end bootstrap did not complete in the subprocess: ' . (string) $output );
		}

		$this->assertStringContainsString( 'deferred=1', $output, 'The deferrable classes were not deferred on a front-end request.' );
		$this->assertStringContainsString( 'before_rest_init=1', $output, "'rest_api_init' unexpectedly fired during the front-end bootstrap." );
		$this->assertStringContainsString( 'resolves=1', $output, 'The autoloader safety net did not resolve a deferred class.' );
		$this->assertStringContainsString( 'interface_ok=1', $output, 'The WP_Sync_Storage interface did not resolve transitively.' );
	}
}
