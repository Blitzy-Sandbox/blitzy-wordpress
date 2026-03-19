/**
 * wp-emoji.js is used to replace emoji with images in browsers when the browser
 * doesn't support emoji natively.
 *
 * @output wp-includes/js/wp-emoji.js
 */

( function( window, settings ) {
	/**
	 * Replaces emoji with images when browsers don't support emoji.
	 *
	 * @since 4.2.0
	 * @access private
	 *
	 * @class
	 *
	 * @see  Twitter Emoji library
	 * @link https://github.com/twitter/twemoji
	 *
	 * @return {Object} The wpEmoji parse and test functions.
	 */
	function wpEmoji() {
		/*
		 * Early exit for browsers with full native emoji support.
		 * When the browser renders all emoji natively, no Twemoji replacement,
		 * MutationObserver, or library loading is needed. Return lightweight stub
		 * functions that preserve the public API contract with zero runtime cost.
		 */
		if ( settings.supports.everything ) {
			return {
				parse: function( obj ) { return obj; },
				test: function() { return false; }
			};
		}

		var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver,

		// Compression and maintain local scope.
		document = window.document,

		// Private.
		twemoji, timer,
		loaded = false,
		count = 0,

		/**
		 * Initialization state machine tracking Twemoji loading progress.
		 * Transitions: 'uninitialized' → 'loading' → 'ready'.
		 *
		 * @type {string}
		 */
		initState = 'uninitialized',

		/**
		 * Queue of DOM element parse operations requested before Twemoji was
		 * available. Each entry is { object: HTMLElement, args: Object|undefined }.
		 * String parse calls cannot be meaningfully queued because the caller
		 * already received the unmodified return value, so only DOM element
		 * operations are stored here.
		 *
		 * @type {Array}
		 */
		parseQueue = [];

		/**
		 * Detect if the browser supports SVG.
		 *
		 * @since 4.6.0
		 * @private
		 *
		 * @see Modernizr
		 * @link https://github.com/Modernizr/Modernizr/blob/master/feature-detects/svg/asimg.js
		 *
		 * @return {boolean} True if the browser supports svg, false if not.
		 */
		function browserSupportsSvgAsImage() {
			if ( !! document.implementation.hasFeature ) {
				return document.implementation.hasFeature( 'http://www.w3.org/TR/SVG11/feature#Image', '1.1' );
			}

			// document.implementation.hasFeature is deprecated. It can be presumed
			// if future browsers remove it, the browser will support SVGs as images.
			return true;
		}

		/**
		 * Initializes emoji replacement infrastructure.
		 *
		 * Polls for the Twemoji library on window.twemoji, sets up a MutationObserver
		 * to watch for dynamically added content, and performs the initial parse of
		 * document.body. This function is NOT called automatically at construction
		 * time — it is triggered lazily by the first call to parse() that requires
		 * emoji replacement, avoiding all setup cost on pages where no emoji content
		 * is encountered.
		 *
		 * @since 4.2.0
		 * @private
		 */
		function load() {
			var queued;

			if ( loaded ) {
				return;
			}

			initState = 'loading';

			// Ensure twemoji is available on the global window before proceeding.
			if ( typeof window.twemoji === 'undefined' ) {
				// Break if waiting for longer than 30 seconds.
				if ( count > 600 ) {
					return;
				}

				// Still waiting.
				window.clearTimeout( timer );
				timer = window.setTimeout( load, 50 );
				count++;

				return;
			}

			twemoji = window.twemoji;
			loaded = true;
			initState = 'ready';

			// Initialize the mutation observer, which checks all added nodes for
			// replaceable emoji characters.
			if ( MutationObserver ) {
				new MutationObserver( function( mutationRecords ) {
					var i = mutationRecords.length,
						addedNodes, removedNodes, ii, node;

					while ( i-- ) {
						addedNodes = mutationRecords[ i ].addedNodes;
						removedNodes = mutationRecords[ i ].removedNodes;
						ii = addedNodes.length;

						/*
						 * Checks if an image has been replaced by a text element
						 * with the same text as the alternate description of the replaced image.
						 * (presumably because the image could not be loaded).
						 * If it is, do absolutely nothing.
						 *
						 * Node type 3 is a TEXT_NODE.
						 *
						 * @link https://developer.mozilla.org/en-US/docs/Web/API/Node/nodeType
						 */
						if (
							ii === 1 && removedNodes.length === 1 &&
							addedNodes[0].nodeType === 3 &&
							removedNodes[0].nodeName === 'IMG' &&
							addedNodes[0].data === removedNodes[0].alt &&
							'load-failed' === removedNodes[0].getAttribute( 'data-error' )
						) {
							return;
						}

						// Loop through all the added nodes.
						while ( ii-- ) {
							node = addedNodes[ ii ];

							// Node type 3 is a TEXT_NODE.
							if ( node.nodeType === 3 ) {
								if ( ! node.parentNode ) {
									continue;
								}

								node = node.parentNode;
							}

							if ( test( node.textContent ) ) {
								parse( node );
							}
						}
					}
				} ).observe( document.body, {
					childList: true,
					subtree: true
				} );
			}

			parse( document.body );

			// Process any DOM element parse operations that were queued while
			// waiting for Twemoji to become available.
			while ( parseQueue.length ) {
				queued = parseQueue.shift();
				parse( queued.object, queued.args );
			}
		}

		/**
		 * Tests if a text string contains emoji characters.
		 *
		 * @since 4.3.0
		 *
		 * @memberOf wp.emoji
		 *
		 * @param {string} text The string to test.
		 *
		 * @return {boolean} Whether the string contains emoji characters.
		 */
		function test( text ) {
			// Single char. U+20E3 to detect keycaps. U+00A9 "copyright sign" and U+00AE "registered sign" not included.
			var single = /[\u203C\u2049\u20E3\u2122\u2139\u2194-\u2199\u21A9\u21AA\u2300\u231A\u231B\u2328\u2388\u23CF\u23E9-\u23F3\u23F8-\u23FA\u24C2\u25AA\u25AB\u25B6\u25C0\u25FB-\u25FE\u2600-\u2604\u260E\u2611\u2614\u2615\u2618\u261D\u2620\u2622\u2623\u2626\u262A\u262E\u262F\u2638\u2639\u263A\u2648-\u2653\u2660\u2663\u2665\u2666\u2668\u267B\u267F\u2692\u2693\u2694\u2696\u2697\u2699\u269B\u269C\u26A0\u26A1\u26AA\u26AB\u26B0\u26B1\u26BD\u26BE\u26C4\u26C5\u26C8\u26CE\u26CF\u26D1\u26D3\u26D4\u26E9\u26EA\u26F0-\u26F5\u26F7-\u26FA\u26FD\u2702\u2705\u2708-\u270D\u270F\u2712\u2714\u2716\u271D\u2721\u2728\u2733\u2734\u2744\u2747\u274C\u274E\u2753\u2754\u2755\u2757\u2763\u2764\u2795\u2796\u2797\u27A1\u27B0\u27BF\u2934\u2935\u2B05\u2B06\u2B07\u2B1B\u2B1C\u2B50\u2B55\u3030\u303D\u3297\u3299]/,
			// Surrogate pair range. Only tests for the second half.
			pair = /[\uDC00-\uDFFF]/;

			if ( text ) {
				return  pair.test( text ) || single.test( text );
			}

			return false;
		}

		/**
		 * Parses any emoji characters into Twemoji images.
		 *
		 * - When passed an element the emoji characters are replaced inline.
		 * - When passed a string the emoji characters are replaced and the result is
		 *   returned.
		 *
		 * @since 4.2.0
		 *
		 * @memberOf wp.emoji
		 *
		 * @param {HTMLElement|string} object The element or string to parse.
		 * @param {Object}             args   Additional options for Twemoji.
		 *
		 * @return {HTMLElement|string} A string where all emoji are now image tags of
		 *                              emoji. Or the element that was passed as the first argument.
		 */
		function parse( object, args ) {
			var params;

			/*
			 * If the browser has full support or our object is not what was
			 * expected, we do not parse anything.
			 */
			if ( settings.supports.everything || ! object ||
				( 'string' !== typeof object && ( ! object.childNodes || ! object.childNodes.length ) ) ) {

				return object;
			}

			/*
			 * Trigger lazy initialization on the first parse call that needs work.
			 * This starts the Twemoji polling loop and defers MutationObserver
			 * setup until emoji replacement is actually requested.
			 */
			if ( initState === 'uninitialized' ) {
				load();
			}

			/*
			 * If Twemoji is not yet available (still loading via the polling loop),
			 * queue DOM element parse operations for processing once the library is
			 * ready. String parse calls return unchanged because the caller already
			 * received the return value and cannot benefit from deferred processing.
			 */
			if ( ! twemoji ) {
				if ( 'string' !== typeof object ) {
					parseQueue.push( { object: object, args: args } );
				}
				return object;
			}

			// Compose the params for the twitter emoji library.
			args = args || {};
			params = {
				base: browserSupportsSvgAsImage() ? settings.svgUrl : settings.baseUrl,
				ext:  browserSupportsSvgAsImage() ? settings.svgExt : settings.ext,
				className: args.className || 'emoji',
				callback: function( icon, options ) {
					// Ignore some standard characters that TinyMCE recommends in its character map.
					switch ( icon ) {
						case 'a9':
						case 'ae':
						case '2122':
						case '2194':
						case '2660':
						case '2663':
						case '2665':
						case '2666':
							return false;
					}

					if ( settings.supports.everythingExceptFlag &&
						! /^1f1(?:e[6-9a-f]|f[0-9a-f])-1f1(?:e[6-9a-f]|f[0-9a-f])$/.test( icon ) && // Country flags.
						! /^(1f3f3-fe0f-200d-1f308|1f3f4-200d-2620-fe0f)$/.test( icon )             // Rainbow and pirate flags.
					) {
						return false;
					}

					return ''.concat( options.base, icon, options.ext );
				},
				attributes: function() {
					return {
						role: 'img'
					};
				},
				onerror: function() {
					if ( twemoji.parentNode ) {
						this.setAttribute( 'data-error', 'load-failed' );
						twemoji.parentNode.replaceChild( document.createTextNode( twemoji.alt ), twemoji );
					}
				},
				doNotParse: function( node ) {
					if (
						node &&
						node.className &&
						typeof node.className === 'string' &&
						node.className.indexOf( 'wp-exclude-emoji' ) !== -1
					) {
						// Do not parse this node. Emojis will not be replaced in this node and all sub-nodes.
						return true;
					}

					return false;
				}
			};

			if ( typeof args.imgAttr === 'object' ) {
				params.attributes = function() {
					return args.imgAttr;
				};
			}

			return twemoji.parse( object, params );
		}

		/*
		 * load() is NOT called here. It is triggered lazily by the first call
		 * to parse() that encounters content needing emoji replacement. This
		 * avoids MutationObserver setup, Twemoji polling, and document.body
		 * parsing on pages where no emoji replacement is needed.
		 */

		return {
			parse: parse,
			test: test
		};
	}

	window.wp = window.wp || {};

	/**
	 * @namespace wp.emoji
	 */
	window.wp.emoji = new wpEmoji();

} )( window, window._wpemojiSettings );
