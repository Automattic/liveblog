// @vitest-environment node

/**
 * Lexical packages share module-level state (node registry, composer
 * context), so a skewed set installs two copies of `lexical` and the editor
 * fails at runtime (e.g. error #365) while the build still passes.
 */

import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const { packages } = JSON.parse(
	readFileSync(
		new URL( '../../../../package-lock.json', import.meta.url ),
		'utf8'
	)
);

const lexicalEntries = Object.entries( packages ).filter( ( [ path ] ) =>
	/(^|\/)node_modules\/(lexical|@lexical\/[^/]+)$/.test( path )
);

describe( 'Lexical dependency set', () => {
	it( 'installs a single copy of lexical', () => {
		expect(
			lexicalEntries
				.map( ( [ path ] ) => path )
				.filter( ( path ) => path.endsWith( 'node_modules/lexical' ) )
		).toEqual( [ 'node_modules/lexical' ] );
	} );

	it( 'keeps every @lexical/* package on the same version as lexical', () => {
		const core = packages[ 'node_modules/lexical' ].version;
		const skewed = lexicalEntries
			.filter( ( [ , meta ] ) => meta.version !== core )
			.map( ( [ path, meta ] ) => `${ path }@${ meta.version }` );

		expect( skewed ).toEqual( [] );
	} );
} );
