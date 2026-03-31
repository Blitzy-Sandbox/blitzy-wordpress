/**
 * @output wp-includes/js/wp-emoji-loader.js
 */

/* eslint-env es6 */
/* global requestIdleCallback */

// Note: This is loaded as a script module, so there is no need for an IIFE to prevent pollution of the global scope.

/**
 * Emoji Settings as exported in PHP via _print_emoji_detection_script().
 * @typedef WPEmojiSettings
 * @type {object}
 * @property {?object} source
 * @property {?string} source.concatemoji
 * @property {?string} source.twemoji
 * @property {?string} source.wpemoji
 */

const settings = /** @type {WPEmojiSettings} */ (
	JSON.parse( document.getElementById( 'wp-emoji-settings' ).textContent )
);

// For compatibility with other scripts that read from this global, in particular wp-includes/js/wp-emoji.js (source file: js/_enqueues/wp/emoji.js).
window._wpemojiSettings = settings;

/**
 * Support tests.
 * @typedef SupportTests
 * @type {object}
 * @property {?boolean} flag
 * @property {?boolean} emoji
 */

const sessionStorageKey = 'wpEmojiSettingsSupports';
const tests = [ 'flag', 'emoji' ];

/**
 * Checks whether the browser supports offloading to a Worker.
 *
 * @since 6.3.0
 *
 * @private
 *
 * @returns {boolean}
 */
function supportsWorkerOffloading() {
	return (
		typeof Worker !== 'undefined' &&
		typeof OffscreenCanvas !== 'undefined' &&
		typeof URL !== 'undefined' &&
		URL.createObjectURL &&
		typeof Blob !== 'undefined'
	);
}

/**
 * @typedef SessionSupportTests
 * @type {object}
 * @property {number} timestamp
 * @property {SupportTests} supportTests
 */

/**
 * Get support tests from session.
 *
 * @since 6.3.0
 *
 * @private
 *
 * @returns {?SupportTests} Support tests, or null if not set or older than 1 week.
 */
function getSessionSupportTests() {
	try {
		/** @type {SessionSupportTests} */
		const item = JSON.parse(
			sessionStorage.getItem( sessionStorageKey )
		);
		if (
			typeof item === 'object' &&
			typeof item.timestamp === 'number' &&
			new Date().valueOf() < item.timestamp + 604800 && // Note: Number is a week in seconds.
			typeof item.supportTests === 'object'
		) {
			return item.supportTests;
		}
	} catch ( e ) {}
	return null;
}

/**
 * Persist the supports in session storage.
 *
 * @since 6.3.0
 *
 * @private
 *
 * @param {SupportTests} supportTests Support tests.
 */
function setSessionSupportTests( supportTests ) {
	try {
		/** @type {SessionSupportTests} */
		const item = {
			supportTests: supportTests,
			timestamp: new Date().valueOf()
		};

		sessionStorage.setItem(
			sessionStorageKey,
			JSON.stringify( item )
		);
	} catch ( e ) {}
}

/**
 * Checks if two sets of Emoji characters render the same visually.
 *
 * This is used to determine if the browser is rendering an emoji with multiple data points
 * correctly. set1 is the emoji in the correct form, using a zero-width joiner. set2 is the emoji
 * in the incorrect form, using a zero-width space. If the two sets render the same, then the browser
 * does not support the emoji correctly.
 *
 * This function may be serialized to run in a Worker. Therefore, it cannot refer to variables from the containing
 * scope. Everything must be passed by parameters.
 *
 * @since 4.9.0
 *
 * @private
 *
 * @param {CanvasRenderingContext2D} context 2D Context.
 * @param {string} set1 Set of Emoji to test.
 * @param {string} set2 Set of Emoji to test.
 *
 * @return {boolean} True if the two sets render the same.
 */
function emojiSetsRenderIdentically( context, set1, set2 ) {
	// Cleanup from previous test.
	context.clearRect( 0, 0, context.canvas.width, context.canvas.height );
	context.fillText( set1, 0, 0 );
	const rendered1 = new Uint32Array(
		context.getImageData(
			0,
			0,
			context.canvas.width,
			context.canvas.height
		).data
	);

	// Cleanup from previous test.
	context.clearRect( 0, 0, context.canvas.width, context.canvas.height );
	context.fillText( set2, 0, 0 );
	const rendered2 = new Uint32Array(
		context.getImageData(
			0,
			0,
			context.canvas.width,
			context.canvas.height
		).data
	);

	return rendered1.every( ( rendered2Data, index ) => {
		return rendered2Data === rendered2[ index ];
	} );
}

/**
 * Checks if the center point of a single emoji is empty.
 *
 * This is used to determine if the browser is rendering an emoji with a single data point
 * correctly. The center point of an incorrectly rendered emoji will be empty. A correctly
 * rendered emoji will have a non-zero value at the center point.
 *
 * This function may be serialized to run in a Worker. Therefore, it cannot refer to variables from the containing
 * scope. Everything must be passed by parameters.
 *
 * @since 6.8.2
 *
 * @private
 *
 * @param {CanvasRenderingContext2D} context 2D Context.
 * @param {string} emoji Emoji to test.
 *
 * @return {boolean} True if the center point is empty.
 */
function emojiRendersEmptyCenterPoint( context, emoji ) {
	// Cleanup from previous test.
	context.clearRect( 0, 0, context.canvas.width, context.canvas.height );
	context.fillText( emoji, 0, 0 );

	// Test if the center point (16, 16) is empty (0,0,0,0).
	const centerPoint = context.getImageData(16, 16, 1, 1);
	for ( let i = 0; i < centerPoint.data.length; i++ ) {
		if ( centerPoint.data[ i ] !== 0 ) {
			// Stop checking the moment it's known not to be empty.
			return false;
		}
	}

	return true;
}

/**
 * Determines if the browser properly renders Emoji that Twemoji can supplement.
 *
 * This function may be serialized to run in a Worker. Therefore, it cannot refer to variables from the containing
 * scope. Everything must be passed by parameters.
 *
 * @since 4.2.0
 *
 * @private
 *
 * @param {CanvasRenderingContext2D} context 2D Context.
 * @param {string} type Whether to test for support of "flag" or "emoji".
 * @param {Function} emojiSetsRenderIdentically Reference to emojiSetsRenderIdentically function, needed due to minification.
 * @param {Function} emojiRendersEmptyCenterPoint Reference to emojiRendersEmptyCenterPoint function, needed due to minification.
 *
 * @return {boolean} True if the browser can render emoji, false if it cannot.
 */
function browserSupportsEmoji( context, type, emojiSetsRenderIdentically, emojiRendersEmptyCenterPoint ) {
	let isIdentical;

	switch ( type ) {
		case 'flag':
			/*
			 * Test for Transgender flag compatibility. Added in Unicode 13.
			 *
			 * To test for support, we try to render it, and compare the rendering to how it would look if
			 * the browser doesn't render it correctly (white flag emoji + transgender symbol).
			 */
			isIdentical = emojiSetsRenderIdentically(
				context,
				'\uD83C\uDFF3\uFE0F\u200D\u26A7\uFE0F', // as a zero-width joiner sequence
				'\uD83C\uDFF3\uFE0F\u200B\u26A7\uFE0F' // separated by a zero-width space
			);

			if ( isIdentical ) {
				return false;
			}

			/*
			 * Test for Sark flag compatibility. This is the least supported of the letter locale flags,
			 * so gives us an easy test for full support.
			 *
			 * To test for support, we try to render it, and compare the rendering to how it would look if
			 * the browser doesn't render it correctly ([C] + [Q]).
			 */
			isIdentical = emojiSetsRenderIdentically(
				context,
				'\uD83C\uDDE8\uD83C\uDDF6', // as the sequence of two code points
				'\uD83C\uDDE8\u200B\uD83C\uDDF6' // as the two code points separated by a zero-width space
			);

			if ( isIdentical ) {
				return false;
			}

			/*
			 * Test for English flag compatibility. England is a country in the United Kingdom, it
			 * does not have a two letter locale code but rather a five letter sub-division code.
			 *
			 * To test for support, we try to render it, and compare the rendering to how it would look if
			 * the browser doesn't render it correctly (black flag emoji + [G] + [B] + [E] + [N] + [G]).
			 */
			isIdentical = emojiSetsRenderIdentically(
				context,
				// as the flag sequence
				'\uD83C\uDFF4\uDB40\uDC67\uDB40\uDC62\uDB40\uDC65\uDB40\uDC6E\uDB40\uDC67\uDB40\uDC7F',
				// with each code point separated by a zero-width space
				'\uD83C\uDFF4\u200B\uDB40\uDC67\u200B\uDB40\uDC62\u200B\uDB40\uDC65\u200B\uDB40\uDC6E\u200B\uDB40\uDC67\u200B\uDB40\uDC7F'
			);

			return ! isIdentical;
		case 'emoji':
			/*
			 * Is there a large, hairy, humanoid mythical creature living in the browser?
			 *
			 * To test for Emoji 17.0 support, try to render a new emoji: Hairy Creature.
			 *
			 * The hairy creature emoji is a single code point emoji. Testing for browser
			 * support required testing the center point of the emoji to see if it is empty.
			 *
			 * 0xD83E 0x1FAC8 (\uD83E\u1FAC8) == 🫈 Hairy creature.
			 *
			 * When updating this test, please ensure that the emoji is either a single code point
			 * or switch to using the emojiSetsRenderIdentically function and testing with a zero-width
			 * joiner vs a zero-width space.
			 */
			const notSupported = emojiRendersEmptyCenterPoint( context, '\uD83E\u1FAC8' );
			return ! notSupported;
	}

	return false;
}

/**
 * Checks emoji support tests.
 *
 * This function may be serialized to run in a Worker. Therefore, it cannot refer to variables from the containing
 * scope. Everything must be passed by parameters.
 *
 * @since 6.3.0
 *
 * @private
 *
 * @param {string[]} tests Tests.
 * @param {Function} browserSupportsEmoji Reference to browserSupportsEmoji function, needed due to minification.
 * @param {Function} emojiSetsRenderIdentically Reference to emojiSetsRenderIdentically function, needed due to minification.
 * @param {Function} emojiRendersEmptyCenterPoint Reference to emojiRendersEmptyCenterPoint function, needed due to minification.
 *
 * @return {SupportTests} Support tests.
 */
function testEmojiSupports( tests, browserSupportsEmoji, emojiSetsRenderIdentically, emojiRendersEmptyCenterPoint ) {
	let canvas;
	if (
		typeof WorkerGlobalScope !== 'undefined' &&
		self instanceof WorkerGlobalScope
	) {
		canvas = new OffscreenCanvas( 300, 150 ); // Dimensions are default for HTMLCanvasElement.
	} else {
		canvas = document.createElement( 'canvas' );
	}

	const context = canvas.getContext( '2d', { willReadFrequently: true } );

	/*
	 * Chrome on OS X added native emoji rendering in M41. Unfortunately,
	 * it doesn't work when the font is bolder than 500 weight. So, we
	 * check for bold rendering support to avoid invisible emoji in Chrome.
	 */
	context.textBaseline = 'top';
	context.font = '600 32px Arial';

	const supports = {};
	tests.forEach( ( test ) => {
		supports[ test ] = browserSupportsEmoji( context, test, emojiSetsRenderIdentically, emojiRendersEmptyCenterPoint );
	} );
	return supports;
}

/**
 * Adds a script to the head of the document.
 *
 * @ignore
 *
 * @since 4.2.0
 *
 * @param {string} src The url where the script is located.
 *
 * @return {void}
 */
function addScript( src ) {
	const script = document.createElement( 'script' );
	script.src = src;
	script.defer = true;
	document.head.appendChild( script );
}

settings.supports = {
	everything: true,
	everythingExceptFlag: true
};

/**
 * Performs a quick single-character canvas test for native emoji support.
 *
 * This is much faster than the full detection pipeline as it only tests a single
 * commonly-supported emoji (grinning face U+1F600). If the browser can render this
 * character, it is a strong indicator of general native emoji support, avoiding
 * the expensive multi-character canvas comparisons and potential Worker offloading.
 *
 * @since 7.0.0
 *
 * @private
 *
 * @return {boolean} True if the browser appears to natively support emoji rendering.
 */
function quickNativeEmojiCheck() {
	try {
		const canvas = document.createElement( 'canvas' );
		const ctx = canvas.getContext( '2d', { willReadFrequently: true } );
		if ( ! ctx ) {
			return false;
		}
		ctx.textBaseline = 'top';
		ctx.font = '32px Arial';
		// Test a commonly supported emoji: grinning face (U+1F600).
		ctx.fillText( '\uD83D\uDE00', 0, 0 );
		const data = ctx.getImageData( 16, 16, 1, 1 ).data;
		// Non-zero alpha channel means the emoji was rendered.
		return data[ 3 ] > 0;
	} catch ( e ) {
		return false;
	}
}

/**
 * Checks if the document body contains emoji characters.
 *
 * Scans the document body text content for characters in common emoji Unicode ranges.
 * Covers emoticons (U+1F600-U+1F64F), miscellaneous symbols (U+1F300-U+1F5FF),
 * transport and map symbols (U+1F680-U+1F6FF), flags (U+1F1E0-U+1F1FF),
 * supplemental symbols (U+1F900-U+1FAFF), variation selectors (U+FE0F),
 * zero-width joiners (U+200D), combining enclosing keycaps (U+20E3),
 * and other common emoji-related blocks (U+2600-U+27BF).
 *
 * In JavaScript's UCS-2 string representation, emoji from supplementary planes
 * (>= U+10000) appear as surrogate pairs: high surrogates \uD83C-\uD83E
 * followed by low surrogates \uDC00-\uDFFF.
 *
 * @since 7.0.0
 *
 * @private
 *
 * @return {boolean} True if emoji characters are detected in the document body.
 */
function documentContainsEmoji() {
	const text = document.body ? document.body.textContent : '';
	return /[\uD83C-\uD83E][\uDC00-\uDFFF]|\u200D|\uFE0F|\u20E3|[\u2600-\u27BF]/.test( text );
}

/**
 * Runs the emoji detection pipeline with layered early-exit optimizations.
 *
 * This function implements a multi-tier detection strategy to minimize main-thread
 * work on modern browsers and pages without emoji content:
 *
 * 1. Content detection — skip entirely if no emoji characters in the document body
 *    and no polyfill source is configured by the PHP side.
 * 2. Session cache — use cached results from a previous page load (sessionStorage).
 * 3. Quick native check — fast single-character canvas test that avoids the expensive
 *    full detection pipeline on browsers with native emoji support.
 * 4. Full detection — Worker-offloaded or main-thread canvas testing of multiple
 *    emoji categories (flag and general emoji).
 *
 * @since 7.0.0
 *
 * @private
 */
function runEmojiDetection() {
	/*
	 * Early exit: if the document body contains no emoji characters and the
	 * PHP side has not configured a polyfill source URL (meaning emoji support
	 * was not explicitly enabled for this page), skip the detection pipeline.
	 *
	 * The default settings.supports values (everything: true, everythingExceptFlag: true)
	 * remain, ensuring no polyfill script is loaded unnecessarily.
	 */
	const emojiSourceConfigured = settings.source && ( settings.source.concatemoji || ( settings.source.wpemoji && settings.source.twemoji ) );
	if ( ! documentContainsEmoji() && ! emojiSourceConfigured ) {
		return;
	}

	// Obtain the emoji support from the browser, asynchronously when possible.
	new Promise( ( resolve ) => {
		// Fast path: use cached session results from a previous page load.
		let supportTests = getSessionSupportTests();
		if ( supportTests ) {
			resolve( supportTests );
			return;
		}

		/*
		 * Quick native emoji check: a fast single-character canvas test.
		 * If the browser can render a basic emoji natively, skip the expensive
		 * full detection pipeline (which tests flag emoji, newer Unicode emoji, etc.).
		 * Cache the positive result so subsequent page loads use the session fast path.
		 */
		if ( quickNativeEmojiCheck() ) {
			supportTests = {};
			tests.forEach( ( test ) => {
				supportTests[ test ] = true;
			} );
			setSessionSupportTests( supportTests );
			resolve( supportTests );
			return;
		}

		// Full detection: try Worker offloading first for non-blocking execution.
		if ( supportsWorkerOffloading() ) {
			try {
				// Note that the functions are being passed as arguments due to minification.
				const workerScript =
					'postMessage(' +
					testEmojiSupports.toString() +
					'(' +
					[
						JSON.stringify( tests ),
						browserSupportsEmoji.toString(),
						emojiSetsRenderIdentically.toString(),
						emojiRendersEmptyCenterPoint.toString()
					].join( ',' ) +
					'));';
				const blob = new Blob( [ workerScript ], {
					type: 'text/javascript'
				} );
				const worker = new Worker( URL.createObjectURL( blob ), { name: 'wpTestEmojiSupports' } );
				worker.onmessage = ( event ) => {
					supportTests = event.data;
					setSessionSupportTests( supportTests );
					worker.terminate();
					resolve( supportTests );
				};
				return;
			} catch ( e ) {}
		}

		// Fallback: main-thread canvas testing.
		supportTests = testEmojiSupports( tests, browserSupportsEmoji, emojiSetsRenderIdentically, emojiRendersEmptyCenterPoint );
		setSessionSupportTests( supportTests );
		resolve( supportTests );
	} )
		// Once the browser emoji support has been obtained from the session, finalize the settings.
		.then( ( supportTests ) => {
			/*
			 * Tests the browser support for flag emojis and other emojis, and adjusts the
			 * support settings accordingly.
			 */
			for ( const test in supportTests ) {
				settings.supports[ test ] = supportTests[ test ];

				settings.supports.everything =
					settings.supports.everything && settings.supports[ test ];

				if ( 'flag' !== test ) {
					settings.supports.everythingExceptFlag =
						settings.supports.everythingExceptFlag &&
						settings.supports[ test ];
				}
			}

			settings.supports.everythingExceptFlag =
				settings.supports.everythingExceptFlag &&
				! settings.supports.flag;

			// When the browser can not render everything we need to load a polyfill.
			if ( ! settings.supports.everything ) {
				const src = settings.source || {};

				if ( src.concatemoji ) {
					addScript( src.concatemoji );
				} else if ( src.wpemoji && src.twemoji ) {
					addScript( src.twemoji );
					addScript( src.wpemoji );
				}
			}
		} );
}

/*
 * Defer emoji detection to avoid blocking initial page render.
 * Uses requestIdleCallback when available to run during browser idle periods,
 * falling back to setTimeout for browsers without requestIdleCallback support.
 */
if ( typeof requestIdleCallback === 'function' ) {
	requestIdleCallback( runEmojiDetection );
} else {
	setTimeout( runEmojiDetection, 0 );
}
