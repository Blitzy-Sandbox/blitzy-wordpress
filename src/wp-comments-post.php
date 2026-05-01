<?php
/**
 * Handles Comment Post to WordPress and prevents duplicate comment posting.
 *
 * Accepts POST submissions from comment forms, validates and persists the
 * comment via wp_handle_comment_submission(), fires the set_comment_cookies
 * hook, and redirects safely to the comment permalink (or to a moderation
 * notice when cookies were declined and the comment is held).
 *
 * @package WordPress
 */

// SECTION: HTTP method gate (only POST is accepted).
if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
	// Inferred: emit a 405 in the request's own protocol version when known; fall back to HTTP/1.0 for unknown protocols. Assumption: based on the in_array() whitelist of HTTP/1.1, HTTP/2, HTTP/2.0, HTTP/3.
	$protocol = $_SERVER['SERVER_PROTOCOL'];
	if ( ! in_array( $protocol, array( 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3' ), true ) ) {
		$protocol = 'HTTP/1.0';
	}

	// RFC 9110 §15.5.6: 405 responses MUST include an Allow header listing acceptable methods.
	header( 'Allow: POST' );
	header( "$protocol 405 Method Not Allowed" );
	header( 'Content-Type: text/plain' );
	exit;
}

/** Sets up the WordPress Environment. */
require __DIR__ . '/wp-load.php';

// SECURITY: refuse intermediary caches; comment submissions must always reach the origin.
nocache_headers();

// SECTION: Submission.
// wp_handle_comment_submission() validates, sanitizes, dedupes, and persists; returns WP_Comment on success or WP_Error on failure. See src/wp-includes/comment.php.
$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
// Error response: surface as wp_die() with the original error data as HTTP status when present.
if ( is_wp_error( $comment ) ) {
	// QUIRK: WP_Error->get_error_data() may return any type; cast to int because wp_die() expects a numeric HTTP status.
	$data = (int) $comment->get_error_data();
	if ( ! empty( $data ) ) {
		wp_die(
			'<p>' . $comment->get_error_message() . '</p>',
			__( 'Comment Submission Failure' ),
			array(
				'response'  => $data,
				'back_link' => true,
			)
		);
	} else {
		exit;
	}
}

// SECTION: Set comment cookies and resolve redirect target.
// $cookies_consent === true when the visitor checked the consent box; controls whether comment_form_default_fields cookies are persisted.
$user            = wp_get_current_user();
$cookies_consent = ( isset( $_POST['wp-comment-cookies-consent'] ) );

/**
 * Fires after comment cookies are set.
 *
 * @since 3.4.0
 * @since 4.9.6 The `$cookies_consent` parameter was added.
 *
 * @param WP_Comment $comment         Comment object.
 * @param WP_User    $user            Comment author's user object. The user may not exist.
 * @param bool       $cookies_consent Comment author's consent to store cookies.
 */
do_action( 'set_comment_cookies', $comment, $user, $cookies_consent );

// Redirect target: explicit redirect_to from POST overrides the comment permalink; otherwise hop to get_comment_link().
$location = empty( $_POST['redirect_to'] ) ? get_comment_link( $comment ) : $_POST['redirect_to'] . '#comment-' . $comment->comment_ID;

// If user didn't consent to cookies, add specific query arguments to display the awaiting moderation message.
// QUIRK: the moderation-hash query arg lets the moderation notice page verify the visitor matches the original commenter without storing a cookie.
if ( ! $cookies_consent && 'unapproved' === wp_get_comment_status( $comment ) && ! empty( $comment->comment_author_email ) ) {
	$location = add_query_arg(
		array(
			'unapproved'      => $comment->comment_ID,
			'moderation-hash' => wp_hash( $comment->comment_date_gmt ),
		),
		$location
	);
}

/**
 * Filters the location URI to send the commenter after posting.
 *
 * @since 2.0.5
 *
 * @param string     $location The 'redirect_to' URI sent via $_POST.
 * @param WP_Comment $comment  Comment object.
 */
$location = apply_filters( 'comment_post_redirect', $location, $comment );

// SECURITY: wp_safe_redirect() restricts the redirect target to allowed hosts to prevent open-redirect abuse.
wp_safe_redirect( $location );
exit;
