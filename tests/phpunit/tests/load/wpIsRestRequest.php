<?php

/**
 * Tests for wp_is_rest_request().
 *
 * @group load
 * @group restapi
 *
 * @covers ::wp_is_rest_request
 */
class Tests_Load_wpIsRestRequest extends WP_UnitTestCase {

	/**
	 * Backup of the original $_SERVER['REQUEST_URI'] value.
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	public function set_up() {
		parent::set_up();

		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
	}

	public function tear_down() {
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		parent::tear_down();
	}

	/**
	 * Ensures the function bails out safely when no request URI is available.
	 */
	public function test_returns_false_when_request_uri_is_empty() {
		unset( $_SERVER['REQUEST_URI'] );

		$this->assertFalse( wp_is_rest_request() );
	}

	/**
	 * Ensures every valid REST URL form is detected against the default prefix.
	 *
	 * @dataProvider data_rest_request_uris
	 *
	 * @param string $request_uri The request URI to inspect.
	 */
	public function test_detects_rest_requests( $request_uri ) {
		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertTrue( wp_is_rest_request(), $request_uri );
	}

	/**
	 * Data provider of request URIs that must be recognized as REST requests.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_rest_request_uris() {
		return array(
			'default prefix path'           => array( '/wp-json/wp/v2/posts' ),
			'exact prefix segment'          => array( '/wp-json' ),
			'prefix with trailing slash'    => array( '/wp-json/' ),
			'prefix path with query string' => array( '/wp-json/wp/v2/posts?per_page=5' ),
			'index.php permalink form'      => array( '/index.php/wp-json/wp/v2/posts' ),
			'rest_route query arg'          => array( '/index.php?rest_route=/wp/v2/posts' ),
			'rest_route on site root'       => array( '/?rest_route=/wp/v2/posts' ),
			'url-encoded rest_route arg'    => array( '/?rest_route=%2Fwp%2Fv2%2Fposts' ),
			'rest_route with extra args'    => array( '/index.php?foo=bar&rest_route=/wp/v2/posts' ),
		);
	}

	/**
	 * Ensures non-REST requests are never misidentified as REST requests.
	 *
	 * @dataProvider data_non_rest_request_uris
	 *
	 * @param string $request_uri The request URI to inspect.
	 */
	public function test_ignores_non_rest_requests( $request_uri ) {
		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertFalse( wp_is_rest_request(), $request_uri );
	}

	/**
	 * Data provider of request URIs that must NOT be recognized as REST requests.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_non_rest_request_uris() {
		return array(
			'front page'                    => array( '/' ),
			'date archive'                  => array( '/2020/05/hello-world/' ),
			'prefix as non-leading segment' => array( '/blog/wp-json/foo' ),
			'prefix as substring suffix'    => array( '/xwp-json/foo' ),
			'prefix as substring prefix'    => array( '/wp-jsonx/foo' ),
			'empty rest_route value'        => array( '/index.php?rest_route=' ),
			'rest_route key without value'  => array( '/?rest_route' ),
			'unrelated query arg'           => array( '/?p=123' ),
			'admin page'                    => array( '/wp-admin/edit.php' ),
		);
	}

	/**
	 * Ensures a REST prefix customized via the `rest_url_prefix` filter is honored.
	 */
	public function test_detects_custom_rest_url_prefix() {
		add_filter(
			'rest_url_prefix',
			static function () {
				return 'api';
			}
		);

		$_SERVER['REQUEST_URI'] = '/api/wp/v2/posts';
		$this->assertTrue( wp_is_rest_request(), 'A path using the custom REST prefix should be detected.' );

		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->assertFalse( wp_is_rest_request(), 'The default prefix must not match when a custom prefix is configured.' );
	}

	/**
	 * Ensures REST requests served from a subdirectory install are detected.
	 */
	public function test_detects_rest_request_in_subdirectory_install() {
		add_filter( 'home_url', array( $this, 'filter_subdirectory_home_url' ) );

		$_SERVER['REQUEST_URI'] = '/subdir/wp-json/wp/v2/posts';
		$this->assertTrue( wp_is_rest_request(), 'A REST path under the home subdirectory should be detected.' );

		$_SERVER['REQUEST_URI'] = '/subdir/index.php/wp-json/wp/v2/posts';
		$this->assertTrue( wp_is_rest_request(), 'An index.php REST path under the home subdirectory should be detected.' );

		$_SERVER['REQUEST_URI'] = '/subdir/index.php?rest_route=/wp/v2/posts';
		$this->assertTrue( wp_is_rest_request(), 'A rest_route request under the home subdirectory should be detected.' );

		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->assertFalse( wp_is_rest_request(), 'A REST path outside the home subdirectory must not be detected.' );

		$_SERVER['REQUEST_URI'] = '/other/wp-json/wp/v2/posts';
		$this->assertFalse( wp_is_rest_request(), 'A path outside the home subdirectory must not be detected.' );

		$_SERVER['REQUEST_URI'] = '/subdir';
		$this->assertFalse( wp_is_rest_request(), 'The home subdirectory root itself is not a REST request.' );
	}

	/**
	 * Returns a subdirectory home URL for the subdirectory install test.
	 *
	 * @return string
	 */
	public function filter_subdirectory_home_url() {
		return 'http://' . WP_TESTS_DOMAIN . '/subdir';
	}

	/**
	 * Ensures the REST_REQUEST constant is authoritative once defined.
	 *
	 * The constant cannot be undefined, so this runs in an isolated process to
	 * avoid leaking the definition into the rest of the suite.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rest_request_constant_short_circuits() {
		define( 'REST_REQUEST', true );

		// A non-REST URL proves the constant short-circuits URL inspection.
		$_SERVER['REQUEST_URI'] = '/2020/05/hello-world/';

		$this->assertTrue( wp_is_rest_request(), 'A defined and truthy REST_REQUEST constant must be authoritative.' );
	}
}
