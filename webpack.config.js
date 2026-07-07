const mediaConfig = require( './tools/webpack/media' );
const developmentConfig = require( './tools/webpack/development' );

module.exports = function (
	env = { environment: 'production', watch: false, buildTarget: false }
) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	if ( ! env.buildTarget ) {
		env.buildTarget = env.mode === 'production' ? 'build/' : 'src/';
	}

	// Only building Core-specific media files and development scripts.
	// Blocks, packages, script modules, and vendors are now sourced from
	// the Gutenberg build (see tools/gutenberg/copy.js).
	// Note: developmentConfig returns an array of configs, so we spread it.
	const config = [
		mediaConfig( env ),
		...developmentConfig( env ),
	];

	// Shared code-splitting policy (F-009): establishes the default webpack
	// `optimization` used to extract shared/common modules out of monolithic
	// entry bundles, reducing the initial admin JavaScript payload toward the
	// >= 30% gzipped transfer-size reduction target (Metric #3). It is applied
	// as a base to every composed config below; because each config's own
	// `optimization` is spread last, per-config settings win. The current media
	// and development (react-refresh) sub-configs each define their own
	// `runtimeChunk`/`splitChunks`, so this policy is a no-op for them and their
	// output stays byte-identical to baseline; future entry configs that do not
	// override these keys inherit code splitting automatically.
	//
	// Chunk names are deterministic and buildTarget-relative so extracted chunks
	// land under `wp-includes/js/`, where Gruntfile.js's clean/copy globs already
	// match them, and never collide with the fixed media-*.js output filenames.
	const splitChunksOptimization = {
		// A single shared runtime chunk keeps webpack's bootstrap/runtime out of
		// the individual entry bundles so it is emitted (and cached) just once.
		runtimeChunk: {
			name: env.buildTarget + 'wp-includes/js/dist/wp-runtime.js',
		},
		splitChunks: {
			// Consider both synchronous and asynchronous chunks when splitting.
			chunks: 'all',
			cacheGroups: {
				// Third-party (node_modules) code shared by two or more chunks.
				vendors: {
					test: /[\\/]node_modules[\\/]/,
					name: env.buildTarget + 'wp-includes/js/dist/wp-vendor.js',
					minChunks: 2,
					priority: -10,
					reuseExistingChunk: true,
				},
				// First-party modules imported by two or more chunks.
				common: {
					name: env.buildTarget + 'wp-includes/js/dist/wp-common.js',
					minChunks: 2,
					priority: -20,
					reuseExistingChunk: true,
				},
			},
		},
	};

	// Apply the shared split policy as a base to each composed config, letting
	// each config's own `optimization` take precedence (spread last). This keeps
	// the media and react-refresh overrides (`runtimeChunk: false` /
	// `splitChunks`) intact while enabling splitting for future configs that opt
	// in simply by not overriding these keys.
	return config.map( ( singleConfig ) => ( {
		...singleConfig,
		optimization: {
			...splitChunksOptimization,
			...singleConfig.optimization,
		},
	} ) );
};
