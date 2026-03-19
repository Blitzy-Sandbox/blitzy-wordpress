<?php
/**
 * @group comment
 */
class Tests_Comment_MetaCache extends WP_UnitTestCase {
	protected $i       = 0;
	protected $queries = 0;

	/**
	 * Performs setup tasks for every test.
	 */
	public function set_up() {
		parent::set_up();
		switch_theme( 'default' );
	}

	/**
	 * @ticket 16894
	 *
	 * @covers ::update_comment_meta
	 */
	public function test_update_comment_meta_cache_should_default_to_eager_priming() {
		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'foo', 'bar' );
		}

		// Clear comment cache, just in case.
		clean_comment_cache( $comment_ids );

		$num_queries = get_num_queries();
		$q           = new WP_Comment_Query(
			array(
				'post_ID' => $p,
			)
		);

		// Eager meta priming: 1 query for IDs, 1 query to prime comment objects, 1 query for meta cache.
		$this->assertSame( 3, get_num_queries() - $num_queries, 'Querying comments with eager meta priming is expected to make three queries' );

		$num_queries = get_num_queries();
		foreach ( $comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}

		// Meta already eagerly primed during the comment query — no additional queries needed.
		$this->assertSame( 0, get_num_queries() - $num_queries, 'Comment meta should already be cached from eager priming' );
	}

	/**
	 * @ticket 57801
	 *
	 * @covers ::update_meta_cache
	 */
	public function test_update_comment_meta_cache_should_default_to_eager_priming_fields_id() {
		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'foo', 'bar' );
		}

		// Clear comment cache, just in case.
		clean_comment_cache( $comment_ids );

		$num_queries = get_num_queries();
		$q           = new WP_Comment_Query(
			array(
				'post_ID' => $p,
				'fields'  => 'ids',
			)
		);

		// Eager meta priming: 1 query for IDs, 1 query for meta cache (no comment object priming for 'ids' fields).
		$this->assertSame( 2, get_num_queries() - $num_queries, 'Querying comment IDs with eager meta priming is expected to make two queries' );

		$num_queries = get_num_queries();
		foreach ( $comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}

		// Meta already eagerly primed during the comment query — no additional queries needed.
		$this->assertSame( 0, get_num_queries() - $num_queries, 'Comment meta should already be cached from eager priming' );
	}

	/**
	 * @ticket 16894
	 *
	 * @covers ::update_comment_meta
	 */
	public function test_update_comment_meta_cache_true() {
		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'foo', 'bar' );
		}

		// Clear comment cache, just in case.
		clean_comment_cache( $comment_ids );

		$num_queries = get_num_queries();
		$q           = new WP_Comment_Query(
			array(
				'post_ID'                   => $p,
				'update_comment_meta_cache' => true,
			)
		);
		// Eager meta priming: 1 query for IDs, 1 for comment objects, 1 for meta cache.
		$this->assertSame( 3, get_num_queries() - $num_queries, 'Comments should be queried, primed, and meta eagerly loaded in three database queries' );

		$num_queries = get_num_queries();
		foreach ( $comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}

		// Meta already eagerly primed during the comment query — no additional queries needed.
		$this->assertSame( 0, get_num_queries() - $num_queries, 'Comment meta should already be cached from eager priming' );
	}

	/**
	 * @ticket 57801
	 *
	 * @covers ::update_comment_meta
	 */
	public function test_update_comment_meta_cache_true_multiple() {
		$posts           = self::factory()->post->create_many( 3 );
		$all_comment_ids = array();
		foreach ( $posts as $p ) {
			$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

			foreach ( $comment_ids as $cid ) {
				update_comment_meta( $cid, 'foo', 'bar' );
				$all_comment_ids[] = $cid;
			}

			$num_queries = get_num_queries();
			$q           = new WP_Comment_Query(
				array(
					'post_ID'                   => $p,
					'update_comment_meta_cache' => true,
				)
			);
			// Eager meta priming: 1 query for comment IDs + 1 query for meta cache
			// (comment objects already cached from factory).
			$this->assertSame( 2, get_num_queries() - $num_queries, 'Comment query with eager meta priming should add two queries' );
		}

		// Meta already eagerly primed per-query — accessing meta requires zero additional queries.
		$num_queries = get_num_queries();
		foreach ( $all_comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}
		$this->assertSame( 0, get_num_queries() - $num_queries, 'Comment meta should already be cached from eager priming during queries' );
	}

	/**
	 * @ticket 16894
	 *
	 * @covers ::update_comment_meta
	 */
	public function test_update_comment_meta_cache_false() {
		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'foo', 'bar' );
		}

		$q = new WP_Comment_Query(
			array(
				'post_ID'                   => $p,
				'update_comment_meta_cache' => false,
			)
		);

		$num_queries = get_num_queries();
		foreach ( $comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}

		$this->assertSame( 3, get_num_queries() - $num_queries );
	}

	/**
	 * @ticket 16894
	 *
	 * @covers ::get_comment_meta
	 */
	public function test_comment_meta_should_be_eagerly_loaded_for_all_comments_in_comments_template() {
		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'sauce', 'fire' );
		}

		$this->go_to( get_permalink( $p ) );

		$this->assertTrue( have_posts() );

		while ( have_posts() ) {
			the_post();

			// Load comments with `comments_template()`.
			$cform = get_echo( 'comments_template' );

			// Meta was eagerly primed during the comment query — no additional queries needed.
			$num_queries = get_num_queries();
			get_comment_meta( $comment_ids[0], 'sauce' );
			$this->assertSame( 0, get_num_queries() - $num_queries );

			// All other comments should also be in cache.
			get_comment_meta( $comment_ids[1], 'sauce' );
			get_comment_meta( $comment_ids[2], 'sauce' );
			$this->assertSame( 0, get_num_queries() - $num_queries );
		}
	}

	/**
	 * @ticket 34047
	 *
	 * @covers ::get_comment_meta
	 * @covers ::wp_lazyload_comment_meta
	 */
	public function test_comment_meta_should_be_lazy_loaded_in_comment_feed_queries() {
		$posts = self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );

		$now      = time();
		$comments = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $posts[0],
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - ( 60 * $i ) ),
				)
			);
		}

		foreach ( $comments as $c ) {
			add_comment_meta( $c, 'foo', 'bar' );
		}

		update_option( 'posts_per_rss', 3 );

		$q = new WP_Query(
			array(
				'feed'         => true,
				'withcomments' => true,
			)
		);

		// First comment will cause the cache to be primed.
		$num_queries = get_num_queries();
		$this->assertSame( 'bar', get_comment_meta( $comments[0], 'foo', 'bar' ) );
		++$num_queries;
		$this->assertSame( $num_queries, get_num_queries() );

		// Second comment from the results should not cause more queries.
		$this->assertSame( 'bar', get_comment_meta( $comments[1], 'foo', 'bar' ) );
		$this->assertSame( $num_queries, get_num_queries() );

		// A comment from outside the results will not be primed.
		$this->assertSame( 'bar', get_comment_meta( $comments[4], 'foo', 'bar' ) );
		++$num_queries;
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 34047
	 *
	 * @covers ::get_comment_meta
	 * @covers ::wp_lazyload_comment_meta
	 */
	public function test_comment_meta_should_be_lazy_loaded_in_single_post_comment_feed_queries() {
		$posts = self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );

		$now      = time();
		$comments = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $posts[0],
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - ( 60 * $i ) ),
				)
			);
		}

		foreach ( $comments as $c ) {
			add_comment_meta( $c, 'foo', 'bar' );
		}

		update_option( 'posts_per_rss', 3 );

		$q = new WP_Query(
			array(
				'feed'         => true,
				'withcomments' => true,
				'p'            => $posts[0],
			)
		);

		// First comment will cause the cache to be primed.
		$num_queries = get_num_queries();
		$this->assertSame( 'bar', get_comment_meta( $comments[0], 'foo', 'bar' ) );
		++$num_queries;
		$this->assertSame( $num_queries, get_num_queries() );

		// Second comment from the results should not cause more queries.
		$this->assertSame( 'bar', get_comment_meta( $comments[1], 'foo', 'bar' ) );
		$this->assertSame( $num_queries, get_num_queries() );

		// A comment from outside the results will not be primed.
		$this->assertSame( 'bar', get_comment_meta( $comments[4], 'foo', 'bar' ) );
		++$num_queries;
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 44467
	 *
	 * @covers ::add_metadata
	 */
	public function test_add_metadata_sets_comments_last_changed() {
		$comment_id = self::factory()->comment->create();

		wp_cache_delete( 'last_changed', 'comment' );

		$this->assertIsInt( add_metadata( 'comment', $comment_id, 'foo', 'bar' ) );
		$this->assertNotFalse( wp_cache_get_last_changed( 'comment' ) );
	}

	/**
	 * @ticket 44467
	 *
	 * @covers ::update_metadata
	 */
	public function test_update_metadata_sets_comments_last_changed() {
		$comment_id = self::factory()->comment->create();

		wp_cache_delete( 'last_changed', 'comment' );

		$this->assertIsInt( update_metadata( 'comment', $comment_id, 'foo', 'bar' ) );
		$this->assertNotFalse( wp_cache_get_last_changed( 'comment' ) );
	}

	/**
	 * @ticket 44467
	 *
	 * @covers ::delete_metadata
	 */
	public function test_delete_metadata_sets_comments_last_changed() {
		$comment_id = self::factory()->comment->create();

		update_metadata( 'comment', $comment_id, 'foo', 'bar' );
		wp_cache_delete( 'last_changed', 'comment' );

		$this->assertTrue( delete_metadata( 'comment', $comment_id, 'foo' ) );
		$this->assertNotFalse( wp_cache_get_last_changed( 'comment' ) );
	}
}
