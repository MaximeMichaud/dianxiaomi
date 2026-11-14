/**
 * A carrier kept on an order after it leaves the selected list stores required
 * fields as one comma-separated string. The meta box must still show each field.
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const assert = require( 'assert' );

const values = {
	'#dianxiaomi_tracking_provider_hidden': 'b2ceurope',
	'#dianxiaomi_tracking_provider_name': 'B2C Europe',
	'#dianxiaomi_tracking_required_fields': 'tracking_postal_code,tracking_destination_country',
	'#dianxiaomi_couriers_selected': '[]',
	'#dianxiaomi_tracking_provider': 'b2ceurope',
};
const shown = [];
const handlers = {};

function jQuery( selector ) {
	// Real jQuery accepts an existing collection: the change handler calls jQuery(this).
	if ( selector && typeof selector === 'object' && typeof selector.val === 'function' ) {
		return selector;
	}
	const api = {
		length: 1,
		val( next ) {
			if ( arguments.length === 0 ) {
				return values[ selector ];
			}
			values[ selector ] = next;
			return api;
		},
		on( eventName, callback ) {
			handlers[ selector + ' ' + eventName ] = callback;
			return api;
		},
		hide() {
			return api;
		},
		show() {
			shown.push( selector );
			return api;
		},
		append() {
			return api;
		},
		selectWoo() {
			return api;
		},
		data() {
			return '';
		},
		parent() {
			return api;
		},
		trigger( eventName ) {
			const callback = handlers[ selector + ' ' + eventName ];
			if ( callback ) {
				callback.call( api );
			}
			return api;
		},
	};
	return api;
}

jQuery.each = function ( collection, callback ) {
	if ( Array.isArray( collection ) ) {
		collection.forEach( ( value, index ) => callback.call( value, index, value ) );
		return;
	}
	Object.keys( collection ).forEach( ( key ) => callback.call( collection[ key ], key, collection[ key ] ) );
};
jQuery.isArray = Array.isArray;

global.jQuery = jQuery;
global.get_couriers = () => [ { slug: 'ups', name: 'UPS', required_fields: [] } ];

const root = path.join( __dirname, '..', '..', 'assets', 'js' );
vm.runInThisContext( fs.readFileSync( path.join( root, 'util.js' ), 'utf8' ) );
vm.runInThisContext( fs.readFileSync( path.join( root, 'admin.js' ), 'utf8' ) );
dianxiaomi_woocommerce_tracking_onload();

assert.ok( shown.includes( '#dianxiaomi_tracking_postal' ), 'postal field stayed hidden: ' + shown.join( ',' ) );
assert.ok( shown.includes( '#dianxiaomi_tracking_destination_country' ), 'country field stayed hidden: ' + shown.join( ',' ) );
assert.strictEqual( values[ '#dianxiaomi_tracking_required_fields' ], 'tracking_postal_code,tracking_destination_country' );

console.log( 'required-fields: ok' );
