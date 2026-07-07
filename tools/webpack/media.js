/**
 * External dependencies
 */
const TerserPlugin = require( 'terser-webpack-plugin' );

/**
 * Internal dependencies
 */
const { baseDir } = require( './shared' );

module.exports = function( env = { environment: 'production', watch: false, buildTarget: false } ) {
	const entry = {
		[ env.buildTarget + 'wp-includes/js/media-audiovideo.js' ]: ['./src/js/_enqueues/wp/media/audiovideo.js'],
		[ env.buildTarget + 'wp-includes/js/media-audiovideo.min.js' ]: ['./src/js/_enqueues/wp/media/audiovideo.js'],
		[ env.buildTarget + 'wp-includes/js/media-grid.js' ]: ['./src/js/_enqueues/wp/media/grid.js'],
		[ env.buildTarget + 'wp-includes/js/media-grid.min.js' ]: ['./src/js/_enqueues/wp/media/grid.js'],
		[ env.buildTarget + 'wp-includes/js/media-models.js' ]: ['./src/js/_enqueues/wp/media/models.js'],
		[ env.buildTarget + 'wp-includes/js/media-models.min.js' ]: ['./src/js/_enqueues/wp/media/models.js'],
		[ env.buildTarget + 'wp-includes/js/media-views.js' ]: ['./src/js/_enqueues/wp/media/views.js'],
		[ env.buildTarget + 'wp-includes/js/media-views.min.js' ]: ['./src/js/_enqueues/wp/media/views.js'],
	};

	const mediaConfig = {
		target: 'browserslist',
		mode: "production",
		cache: true,
		entry,
		output: {
			path: baseDir,
			filename: '[name]',
		},
		optimization: {
			minimize: true,
			moduleIds: 'deterministic',
			// Keep each media bundle self-contained: no separate runtime file.
			// This also neutralizes any global runtimeChunk applied by the
			// composed root webpack.config.js so the independently-enqueued
			// media handles never gain an un-enqueued runtime dependency.
			runtimeChunk: false,
			splitChunks: {
				cacheGroups: {
					// Extract code shared across the NON-minified media entries
					// into a single, deterministically-named common chunk.
					'media-common': {
						name: env.buildTarget + 'wp-includes/js/media-common.js',
						chunks: ( chunk ) =>
							!! chunk.name &&
							chunk.name.endsWith( '.js' ) &&
							! chunk.name.endsWith( '.min.js' ),
						minChunks: 2,
						priority: 10,
						reuseExistingChunk: true,
					},
					// Extract code shared across the MINIFIED media entries into a
					// separate .min.js common chunk so it matches the TerserPlugin
					// `include: /\.min\.js$/` filter and stays minified.
					'media-common-min': {
						name: env.buildTarget + 'wp-includes/js/media-common.min.js',
						chunks: ( chunk ) =>
							!! chunk.name && chunk.name.endsWith( '.min.js' ),
						minChunks: 2,
						priority: 10,
						reuseExistingChunk: true,
					},
				},
			},
			minimizer: [
				new TerserPlugin( {
					include: /\.min\.js$/,
					extractComments: false,
				} ),
			]
		},
		watch: env.watch,
	};

	return mediaConfig;
};
