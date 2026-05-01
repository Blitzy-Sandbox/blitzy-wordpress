<?php
/**
 * Confirms that the activation key that is sent in an email after a user signs
 * up for a new site matches the key for that user and then displays confirmation.
 *
 * Multisite-only endpoint. On single-site installs, redirects to
 * wp_registration_url(). The activation key is read from $_GET['key'],
 * $_POST['key'], or the wp-activate-{COOKIEHASH} cookie (in that priority).
 *
 * @package WordPress
 */

// SECURITY: WP_INSTALLING short-circuits get_option() defaults and skips
// plugin loading; activation must run before site setup completes.
define( 'WP_INSTALLING', true );

/** Sets up the WordPress Environment. */
require __DIR__ . '/wp-load.php';

require __DIR__ . '/wp-blog-header.php';

// SECTION: Multisite gate.
//
// Single-site installs use wp-login.php?action=register; redirect there.
if ( ! is_multisite() ) {
	wp_redirect( wp_registration_url() );
	die();
}

// These error codes are not "real" failures -- they mean the visitor is
// following a stale link to an already-active signup. Render confirmation
// rather than an error page.
$valid_error_codes = array( 'already_active', 'blog_taken' );

// QUIRK: explode-and-list pattern grabs only the path portion of REQUEST_URI
// for cookie scoping; the query string is intentionally discarded. The
// cookie name is per-install (COOKIEHASH derives from siteurl) so multiple
// installs do not collide.
list( $activate_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
$activate_cookie       = 'wp-activate-' . COOKIEHASH;

$key    = '';
$result = null;

// SECTION: Activation key resolution (priority: GET -> POST -> cookie).
//
// SECURITY: tampering check -- if both GET and POST keys are present and
// disagree, refuse the activation.
if ( isset( $_GET['key'] ) && isset( $_POST['key'] ) && $_GET['key'] !== $_POST['key'] ) {
	wp_die( __( 'A key value mismatch has been detected. Please follow the link provided in your activation email.' ), __( 'An error occurred during the activation' ), 400 );
} elseif ( ! empty( $_GET['key'] ) ) {
	$key = sanitize_text_field( $_GET['key'] );
} elseif ( ! empty( $_POST['key'] ) ) {
	$key = sanitize_text_field( $_POST['key'] );
}

if ( $key ) {
	// Strip 'key' from the URL so it disappears from history/referrer headers.
	$redirect_url = remove_query_arg( 'key' );

	// Inferred: this branch fires only when the URL still has *other* query
	// args after 'key' was removed; persist the key in a cookie and redirect
	// to the cleaned URL. Assumption: based on the empty-vs-non-empty
	// redirect target distinction.
	if ( remove_query_arg( false ) !== $redirect_url ) {
		// Persist the activation key in a same-path cookie so the redirect
		// form submission can recover it.
		setcookie( $activate_cookie, $key, 0, $activate_path, COOKIE_DOMAIN, is_ssl(), true );
		wp_safe_redirect( $redirect_url );
		exit;
	} else {
		// Apply the activation: creates the user, optionally creates the
		// site, returns success metadata or WP_Error.
		$result = wpmu_activate_signup( $key );
	}
}

// Cookie fallback: form POSTed without preserving the key in the URL --
// recover from the cookie set above.
if ( null === $result && isset( $_COOKIE[ $activate_cookie ] ) ) {
	$key    = $_COOKIE[ $activate_cookie ];
	$result = wpmu_activate_signup( $key );
	// Erase the activation cookie immediately after consumption (set in past
	// with empty value).
	setcookie( $activate_cookie, ' ', time() - YEAR_IN_SECONDS, $activate_path, COOKIE_DOMAIN, is_ssl(), true );
}

// SECTION: HTTP status code resolution.
if ( null === $result || ( is_wp_error( $result ) && 'invalid_key' === $result->get_error_code() ) ) {
	// 404 for invalid/missing keys; matches the experience of clicking a
	// stale activation email link.
	status_header( 404 );
} elseif ( is_wp_error( $result ) ) {
	$error_code = $result->get_error_code();

	// Other WP_Error codes (expired, malformed, network failure) -> 400 Bad Request.
	if ( ! in_array( $error_code, $valid_error_codes, true ) ) {
		status_header( 400 );
	}
}

// SECURITY: do not cache activation responses -- they reveal account state.
nocache_headers();

// Fix for page title.
// QUIRK: wp() may have set is_404 = true earlier; clear it so the rendered
// page reports as a real 200/4xx response not a 404.
$wp_query->is_404 = false;

/**
 * Fires before the Site Activation page is loaded.
 *
 * @since 3.0.0
 */
do_action( 'activate_header' );

/**
 * Adds an action hook specific to this page.
 *
 * Fires on {@see 'wp_head'}.
 *
 * @since MU (3.0.0)
 */
function do_activate_header() {
	/**
	 * Fires within the `<head>` section of the Site Activation page.
	 *
	 * Fires on the {@see 'wp_head'} action.
	 *
	 * @since 3.0.0
	 */
	do_action( 'activate_wp_head' );
}
add_action( 'wp_head', 'do_activate_header' );

/**
 * Loads styles specific to this page.
 *
 * @since MU (3.0.0)
 */
function wpmu_activate_stylesheet() {
	?>
	<style>
		.wp-activate-container { width: 90%; margin: 0 auto; text-align: start; padding: 24px; box-sizing: border-box; }
		.wp-activate-container form { margin: 24px 0; }
		.wp-activate-container p { font-size: 18px; }
		#key, #submit { font-size: 24px; box-sizing: border-box; margin: 5px 0; }
		#key { width: 100%; direction: ltr; }
		#submit { width: auto; }
		span.h3 { font-weight: 600; }
	</style>
	<?php
}
// SECTION: Hook registrations.
add_action( 'wp_head', 'wpmu_activate_stylesheet' );
add_action( 'wp_head', 'wp_strict_cross_origin_referrer' );
add_filter( 'wp_robots', 'wp_robots_sensitive_page' );

// Renders header-wp-activate.php template part if the active theme provides
// one; otherwise falls back to header.php.
get_header( 'wp-activate' );

// Resolve current network site so subsequent links use the correct
// path/domain.
$blog_details = get_site();

// SECTION: HTML output (form vs success vs error states).
?>

<div id="signup-content" class="widecolumn">
	<div class="wp-activate-container">
	<?php if ( ! $key ) { ?>

		<h2><?php _e( 'Activation Key Required' ); ?></h2>
		<form name="activateform" id="activateform" method="post" action="<?php echo esc_url( network_site_url( $blog_details->path . 'wp-activate.php' ) ); ?>">
			<p>
				<label for="key"><?php _e( 'Activation Key:' ); ?></label>
				<br /><input type="text" name="key" id="key" value="" size="50" autofocus="autofocus" />
			</p>
			<p class="submit">
				<input id="submit" type="submit" name="Submit" class="submit" value="<?php esc_attr_e( 'Activate' ); ?>" />
			</p>
		</form>

		<?php
	} else {
		if ( is_wp_error( $result ) && in_array( $result->get_error_code(), $valid_error_codes, true ) ) {
			$signup = $result->get_error_data();
			?>
			<h2><?php _e( 'Your account is now active!' ); ?></h2>
			<?php
			echo '<p class="lead-in">';
			if ( '' === $signup->domain . $signup->path ) {
				printf(
					/* translators: 1: Login URL, 2: Username, 3: User email address, 4: Lost password URL. */
					__( 'Your account has been activated. You may now <a href="%1$s">log in</a> to the site using your chosen username of &#8220;%2$s&#8221;. Please check your email inbox at %3$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%4$s">reset your password</a>.' ),
					esc_url( network_site_url( $blog_details->path . 'wp-login.php', 'login' ) ),
					esc_html( $signup->user_login ),
					esc_html( $signup->user_email ),
					esc_url( wp_lostpassword_url() )
				);
			} else {
				printf(
					/* translators: 1: Site URL, 2: Username, 3: User email address, 4: Lost password URL. */
					__( 'Your site at %1$s is active. You may now log in to your site using your chosen username of &#8220;%2$s&#8221;. Please check your email inbox at %3$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%4$s">reset your password</a>.' ),
					sprintf( '<a href="http://%1$s">%1$s</a>', esc_url( $signup->domain . $blog_details->path ) ),
					esc_html( $signup->user_login ),
					esc_html( $signup->user_email ),
					esc_url( wp_lostpassword_url() )
				);
			}
			echo '</p>';
		} elseif ( null === $result || is_wp_error( $result ) ) {
			?>
			<h2><?php _e( 'An error occurred during the activation' ); ?></h2>
			<?php if ( is_wp_error( $result ) ) : ?>
				<p><?php echo esc_html( $result->get_error_message() ); ?></p>
			<?php endif; ?>
			<?php
		} else {
			$url  = isset( $result['blog_id'] ) ? esc_url( get_home_url( (int) $result['blog_id'] ) ) : '';
			$user = get_userdata( (int) $result['user_id'] );
			?>
			<h2><?php _e( 'Your account is now active!' ); ?></h2>

			<div id="signup-welcome">
			<p><span class="h3"><?php _e( 'Username:' ); ?></span> <?php echo esc_html( $user->user_login ); ?></p>
			<p><span class="h3"><?php _e( 'Password:' ); ?></span> <?php echo esc_html( $result['password'] ); ?></p>
			</div>

			<?php
			if ( $url && network_home_url( '', 'http' ) !== $url ) :
				switch_to_blog( (int) $result['blog_id'] );
				$login_url = wp_login_url();
				restore_current_blog();
				?>
				<p class="view">
				<?php
					/* translators: 1: Site URL, 2: Login URL. */
					printf( __( 'Your account is now activated. <a href="%1$s">View your site</a> or <a href="%2$s">Log in</a>' ), esc_url( $url ), esc_url( $login_url ) );
				?>
				</p>
			<?php else : ?>
				<p class="view">
				<?php
					printf(
						/* translators: 1: Login URL, 2: Network home URL. */
						__( 'Your account is now activated. <a href="%1$s">Log in</a> or go back to the <a href="%2$s">homepage</a>.' ),
						esc_url( network_site_url( $blog_details->path . 'wp-login.php', 'login' ) ),
						esc_url( network_home_url( $blog_details->path ) )
					);
				?>
				</p>
				<?php
				endif;
		}
	}
	?>
	</div>
</div>
<?php
// Counterpart to the get_header( 'wp-activate' ) call above.
get_footer( 'wp-activate' );
