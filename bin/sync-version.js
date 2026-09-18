/**
 * Syncs the version from package.json into the plugin header/constant and readme.txt.
 *
 * Run automatically by npm's "version" lifecycle (see the "version" script in
 * package.json), which fires after `npm version <patch|minor|major>` bumps
 * package.json but before it commits and tags. This keeps every version string
 * in the repo in lockstep with a single `npm run release -- <bump>` command.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const rootDir = path.resolve( __dirname, '..' );
const pkg = require( path.join( rootDir, 'package.json' ) );
const version = pkg.version;

const pluginFile = path.join( rootDir, 'viget-blocks-toolkit.php' );
const readmeFile = path.join( rootDir, 'readme.txt' );

function replaceOrThrow( content, pattern, replacement, file ) {
	if ( ! pattern.test( content ) ) {
		throw new Error( `Could not find pattern ${ pattern } in ${ file }` );
	}
	return content.replace( pattern, replacement );
}

let pluginContents = fs.readFileSync( pluginFile, 'utf8' );
pluginContents = replaceOrThrow(
	pluginContents,
	/(\* Version:\s*)([^\r\n]+)/,
	`$1${ version }`,
	pluginFile
);
pluginContents = replaceOrThrow(
	pluginContents,
	/(const VGTBT_VERSION\s*=\s*')([^']+)(')/,
	`$1${ version }$3`,
	pluginFile
);
fs.writeFileSync( pluginFile, pluginContents );

let readmeContents = fs.readFileSync( readmeFile, 'utf8' );
readmeContents = replaceOrThrow(
	readmeContents,
	/(Stable tag:\s*)([^\r\n]+)/,
	`$1${ version }`,
	readmeFile
);
fs.writeFileSync( readmeFile, readmeContents );

// eslint-disable-next-line no-console
console.log( `Synced version ${ version } into plugin header and readme.txt` );
