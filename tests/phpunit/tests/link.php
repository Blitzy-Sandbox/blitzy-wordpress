<?php
/**
 * @group link
 */
class Tests_Link extends WP_UnitTestCase {

	public function test_wp_get_shortlink() {
		$post_id  = self::factory()->post->create();
		$post_id2 = self::factory()->post->create();

		// Basic case.
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		unset( $GLOBALS['post'] );

		// Global post is not set.
		$this->assertSame( '', wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( '', wp_get_shortlink( 0 ) );
		$this->assertSame( '', wp_get_shortlink() );

		$GLOBALS['post'] = get_post( $post_id );

		// Global post is set.
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( 0 ) );
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink() );

		// Not the global post.
		$this->assertSame( get_permalink( $post_id2 ), wp_get_shortlink( $post_id2, 'post' ) );

		unset( $GLOBALS['post'] );

		// Global post is not set, once again.
		$this->assertSame( '', wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( '', wp_get_shortlink( 0 ) );
		$this->assertSame( '', wp_get_shortlink() );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		// With a permalink structure set, get_permalink() will no longer match.
		$this->assertNotEquals( get_permalink( $post_id ), wp_get_shortlink( $post_id, 'post' ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		// Global post and permalink structure are set.
		$GLOBALS['post'] = get_post( $post_id );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( 0 ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink() );
	}

	public function test_wp_get_shortlink_with_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		// Basic case.
		// Don't test against get_permalink() since it uses ?page_id= for pages.
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );
	}

	/**
	 * @ticket 26871
	 */
	public function test_wp_get_shortlink_with_home_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post_id );

		$this->assertSame( home_url( '/' ), wp_get_shortlink( $post_id, 'post' ) );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( home_url( '/' ), wp_get_shortlink( $post_id, 'post' ) );
	}

	/**
	 * @ticket 30910
	 */
	public function test_get_permalink_should_not_reveal_post_name_for_post_with_post_status_future() {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		flush_rewrite_rules();

		$p = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);

		$non_pretty_permalink = add_query_arg( 'p', $p, trailingslashit( home_url() ) );

		$this->assertSame( $non_pretty_permalink, get_permalink( $p ) );
	}

	/**
	 * Ensures the request-scoped get_permalink() memoization does not allow a
	 * sample permalink to leak into a later non-sample lookup for a protected
	 * (non-private) post status.
	 *
	 * For a protected status such as 'future', wp_force_plain_post_permalink()
	 * returns a pretty URL only when generating a sample link and a plain "?p="
	 * URL otherwise, so the memo key must distinguish the sample state. This is
	 * a regression guard for that memoization.
	 *
	 * @covers ::get_permalink
	 */
	public function test_get_permalink_memo_should_not_leak_sample_url_for_protected_status() {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		flush_rewrite_rules();

		$p = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);

		$non_pretty_permalink = add_query_arg( 'p', $p, trailingslashit( home_url() ) );

		/*
		 * Prime the memo with a sample link first. get_sample_permalink() sets
		 * the post's filter to 'sample', which forces the pretty URL form for a
		 * protected status; replicate that here on a clone so the cached post
		 * object is not mutated.
		 */
		$sample_post         = clone get_post( $p );
		$sample_post->filter = 'sample';
		$sample_permalink    = get_permalink( $sample_post );

		// The sample link is the pretty form and must differ from the plain URL.
		$this->assertNotSame(
			$non_pretty_permalink,
			$sample_permalink,
			'The sample permalink for a future post should be the pretty form.'
		);

		// A subsequent non-sample lookup must return the plain "?p=" URL and
		// must not return the memoized sample (pretty) URL.
		$this->assertSame( $non_pretty_permalink, get_permalink( $p ) );
	}

	/**
	 * @ticket 30910
	 */
	public function test_get_permalink_should_not_reveal_post_name_for_cpt_with_post_status_future() {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		register_post_type( 'wptests_pt', array( 'public' => true ) );

		flush_rewrite_rules();

		$p = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_type'   => 'wptests_pt',
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);

		$non_pretty_permalink = add_query_arg(
			array(
				'post_type' => 'wptests_pt',
				'p'         => $p,
			),
			trailingslashit( home_url() )
		);

		$this->assertSame( $non_pretty_permalink, get_permalink( $p ) );
	}

	/**
	 * @ticket 1914
	 */
	public function test_unattached_attachment_has_a_pretty_permalink() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'An Attachment!',
				'post_status'    => 'inherit',
			)
		);

		$attachment = get_post( $attachment_id );

		$this->assertSame( home_url( user_trailingslashit( $attachment->post_name ) ), get_permalink( $attachment_id ) );
	}

	/**
	 * @ticket 1914
	 */
	public function test_attachment_attached_to_non_existent_post_type_has_a_pretty_permalink() {
		global $wp_post_types;

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		register_post_type( 'not_a_post_type', array( 'public' => true ) );

		flush_rewrite_rules();

		$post_id = self::factory()->post->create( array( 'post_type' => 'not_a_post_type' ) );

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'An Attachment!',
				'post_status'    => 'inherit',
			)
		);

		$attachment = get_post( $attachment_id );

		$this->assertSame( get_permalink( $post_id ) . user_trailingslashit( $attachment->post_name ), get_permalink( $attachment_id ) );

		foreach ( $wp_post_types as $id => $pt ) {
			if ( 'not_a_post_type' === $pt->name ) {
				unset( $wp_post_types[ $id ] );
				break;
			}
		}

		$this->assertSame( home_url( "/?attachment_id={$attachment->ID}" ), get_permalink( $attachment_id ) );
		// Visit permalink.
		$this->go_to( get_permalink( $attachment_id ) );
		$this->assertQueryTrue( 'is_attachment', 'is_single', 'is_singular' );
	}
}
