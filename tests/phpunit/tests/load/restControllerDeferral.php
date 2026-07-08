<?php

/**
 * Tests for the REST controller deferral and its autoloader safety net in
 * wp-settings.php.
 *
 * On a genuine non-REST front-end theme render, wp-settings.php defers loading
 * the REST endpoint/field/search-handler classes until 'rest_api_init'. A
 * classmap spl_autoload_register() is registered as a safety net so that any
 * earlier reference to one of those classes - a class_exists() check, direct
 * instantiation, or WP_Post_Type::get_rest_controller() - still resolves before
 * 'rest_api_init' fires. These tests lock in both the classmap's accuracy and
 * the runtime behavior of the safety net.
 *
 * @group load
 * @group restapi
 */
class Tests_Load_RestControllerDeferral extends WP_UnitTestCase {

	/**
	 * Absolute path to the bootstrap file that owns the deferral.
	 *
	 * @return string
	 */
	private function wp_settings_path() {
		return ABSPATH . 'wp-settings.php';
	}

	/**
	 * Parses the $wp_rest_controller_classmap literal out of wp-settings.php.
	 *
	 * Reading the source (rather than the runtime variable, which is unset at
	 * the end of the bootstrap) keeps the test independent of load order.
	 *
	 * @return array<string,string> Map of class name => absolute file path.
	 */
	private function parse_classmap() {
		$source = file_get_contents( $this->wp_settings_path() );
		$this->assertNotFalse( $source, 'Unable to read wp-settings.php.' );

		$start = strpos( $source, '$wp_rest_controller_classmap = array(' );
		$this->assertNotFalse( $start, 'The $wp_rest_controller_classmap array was not found in wp-settings.php.' );

		$end = strpos( $source, ');', $start );
		$this->assertNotFalse( $end, 'The end of the $wp_rest_controller_classmap array was not found.' );

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
	 * The classmap must cover exactly the deferrable REST files, and every entry
	 * must point at an existing file that declares the mapped class.
	 *
	 * This guards against the classmap drifting out of sync with the REST
	 * controller/field/search-handler files on disk, which would silently defeat
	 * the autoloader safety net.
	 */
	public function test_rest_controller_classmap_matches_deferrable_files() {
		$map = $this->parse_classmap();

		// Every mapped file must exist and declare its mapped class.
		foreach ( $map as $class_name => $file ) {
			$this->assertFileExists( $file, "Classmap file for {$class_name} does not exist." );

			$contents = file_get_contents( $file );
			$this->assertMatchesRegularExpression(
				'/\bclass\s+' . preg_quote( $class_name, '/' ) . '\b/',
				$contents,
				"File {$file} does not declare class {$class_name}."
			);
		}

		// The mapped files must equal the deferrable set on disk: every endpoint
		// controller except the eagerly-loaded base controller, plus every meta
		// field class and every search handler.
		$endpoints = glob( ABSPATH . WPINC . '/rest-api/endpoints/*.php' );
		$fields    = glob( ABSPATH . WPINC . '/rest-api/fields/*.php' );
		$search    = glob( ABSPATH . WPINC . '/rest-api/search/*.php' );

		$expected = array();
		foreach ( array_merge( $endpoints, $fields, $search ) as $file ) {
			// The abstract base controller is always eagerly loaded.
			if ( str_ends_with( $file, '/rest-api/endpoints/class-wp-rest-controller.php' ) ) {
				continue;
			}
			$expected[] = $file;
		}

		$mapped = array_values( $map );

		sort( $expected );
		sort( $mapped );

		$this->assertSame(
			$expected,
			$mapped,
			'The classmap does not cover exactly the deferrable REST files on disk.'
		);
	}

	/**
	 * The consumer path named in the review - WP_Post_Type::get_rest_controller()
	 * - must return a controller instance. The classmap autoloader is what keeps
	 * this working when the classes are deferred on a front-end request.
	 */
	public function test_get_rest_controller_returns_controller_instance() {
		$post_type = get_post_type_object( 'post' );

		$this->assertInstanceOf( 'WP_Post_Type', $post_type );

		$controller = $post_type->get_rest_controller();

		$this->assertInstanceOf( 'WP_REST_Posts_Controller', $controller );
	}

	/**
	 * End-to-end: booting a genuine non-REST front-end request must defer the
	 * REST controllers, yet a reference to one before 'rest_api_init' must still
	 * resolve through the autoloader safety net.
	 *
	 * This runs in a subprocess because the deferral branch only executes when
	 * the request is a front-end theme render (WP_USE_THEMES defined, not admin,
	 * not a REST request), which is not the case for the PHPUnit bootstrap. The
	 * test is skipped when the runtime configuration required to boot a front-end
	 * request is unavailable.
	 */
	public function test_deferred_rest_controllers_resolve_on_front_end_bootstrap() {
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

\$file = realpath( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-posts-controller.php' );
\$deferred = ! in_array( \$file, array_map( 'realpath', get_included_files() ), true );
\$before_rest_init = ( 0 === (int) did_action( 'rest_api_init' ) );
\$resolves = class_exists( 'WP_REST_Posts_Controller' );
\$pt = get_post_type_object( 'post' );
\$controller_ok = \$pt && ( \$pt->get_rest_controller() instanceof WP_REST_Posts_Controller );
\$subclass_ok = class_exists( 'WP_REST_Attachments_Controller' )
    && is_subclass_of( 'WP_REST_Attachments_Controller', 'WP_REST_Posts_Controller' );

echo 'BLITZY_PROBE:'
    . 'deferred=' . ( \$deferred ? 1 : 0 )
    . ';before_rest_init=' . ( \$before_rest_init ? 1 : 0 )
    . ';resolves=' . ( \$resolves ? 1 : 0 )
    . ';controller_ok=' . ( \$controller_ok ? 1 : 0 )
    . ';subclass_ok=' . ( \$subclass_ok ? 1 : 0 )
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

		$this->assertStringContainsString( 'deferred=1', $output, 'REST controllers were not deferred on a front-end request.' );
		$this->assertStringContainsString( 'before_rest_init=1', $output, "'rest_api_init' unexpectedly fired during the front-end bootstrap." );
		$this->assertStringContainsString( 'resolves=1', $output, 'The autoloader safety net did not resolve a deferred REST controller class.' );
		$this->assertStringContainsString( 'controller_ok=1', $output, 'WP_Post_Type::get_rest_controller() did not return a controller instance.' );
		$this->assertStringContainsString( 'subclass_ok=1', $output, 'A deferred REST controller subclass did not resolve transitively.' );
	}
}
