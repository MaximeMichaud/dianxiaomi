/**
 * The courier dropdown must be alphabetical for a subset, not only for the full catalog.
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const assert = require( 'assert' );

const root = path.join( __dirname, '..', '..', 'assets', 'js' );
vm.runInThisContext( fs.readFileSync( path.join( root, 'util.js' ), 'utf8' ) );
vm.runInThisContext( fs.readFileSync( path.join( root, 'couriers.js' ), 'utf8' ) );

function names( couriers ) {
	return couriers.map( ( courier ) => courier.name );
}

function sortedNames( couriers ) {
	return names( couriers ).sort( ( left, right ) => left.toLowerCase().localeCompare( right.toLowerCase() ) );
}

const fixture = [
	{ name: '17 Post Service' },
	{ name: 'UPS' },
	{ name: 'DHL' },
	{ name: 'FedEx' },
];
assert.deepStrictEqual( names( sort_couriers( fixture.map( ( courier ) => ( { ...courier } ) ) ) ), sortedNames( fixture ) );

const slugs = [ 'jet-ship', 'nipost', 'roadbull', 'tntpost-it' ];
const subset = get_couriers().filter( ( courier ) => slugs.includes( courier.slug ) );
assert.deepStrictEqual( names( sort_couriers( subset.map( ( courier ) => ( { ...courier } ) ) ) ), sortedNames( subset ) );

console.log( 'sort-couriers: ok' );
