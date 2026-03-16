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

	// Enable code splitting for conditionally-loaded modules.
	// Admin common.js is split into core essentials and lazy-loaded feature modules.
	// Emoji detection is deferred to on-demand loading.
	// Customizer JS loads only in Customizer context.
	if ( typeof env.codeSplitting === 'undefined' ) {
		env.codeSplitting = env.mode === 'production';
	}

	// Only building Core-specific media files and development scripts.
	// Blocks, packages, script modules, and vendors are now sourced from
	// the Gutenberg build (see tools/gutenberg/copy.js).
	// Note: developmentConfig returns an array of configs, so we spread it.
	const config = [
		mediaConfig( env ),
		...developmentConfig( env ),
	];

	return config;
};
