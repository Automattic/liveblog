import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	getLastOfObject,
	getFirstOfObject,
	getPollingPages,
	getNewestEntry,
	triggerOembedLoad,
	hasKeyCommand,
	stripKeyCommand,
	addKeyCommand,
} from '../utils';

describe( 'utils', () => {
	const dummyObj = {
		one: {
			data: 'Test 1',
		},
		two: {
			data: 'Test 2',
		},
		three: {
			data: 'Test 3',
		},
	};

	it( 'getLastObjectOf should return the last item in an object', () => {
		expect( getLastOfObject( dummyObj ) ).toEqual( { data: 'Test 3' } );
	} );

	it( 'getFirstOfObject should return the first item in an object', () => {
		expect( getFirstOfObject( dummyObj ) ).toEqual( { data: 'Test 1' } );
	} );

	it( 'getPollingPages should return the correct pages number', () => {
		expect( getPollingPages( 1, false ) ).toEqual( 1 );
		expect( getPollingPages( 4, 8 ) ).toEqual( 8 );
		expect( getPollingPages( 1, 0 ) ).toEqual( 1 );
		expect( getPollingPages( 2, -1 ) ).toEqual( 1 );
	} );

	const olderEntry = { timestamp: 1511136000 };
	const newerEntry = { timestamp: 1511568000 };

	it( 'getNewestEntry should return the newest entry', () => {
		expect( getNewestEntry( olderEntry, newerEntry ) ).toEqual(
			newerEntry
		);
		expect( getNewestEntry( false, false ) ).toBeFalsy();
		expect( getNewestEntry( false, newerEntry ) ).toEqual( newerEntry );
		expect( getNewestEntry( olderEntry, false ) ).toEqual( olderEntry );
		expect( getNewestEntry( newerEntry, olderEntry ) ).toEqual(
			newerEntry
		);
	} );

	describe( 'triggerOembedLoad', () => {
		let mockElement;
		let originalWindow;

		const sdkUrls = {
			facebook:
				'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.5',
			twitter: 'https://platform.twitter.com/widgets.js',
			instagram: 'https://www.instagram.com/embed.js',
			reddit: 'https://embed.reddit.com/widgets.js',
		};

		const injectedScript = ( name ) =>
			document.getElementById( `${ name }-js` );

		beforeEach( () => {
			// Store original window properties
			originalWindow = {
				FB: window.FB,
				twttr: window.twttr,
				instgrm: window.instgrm,
				liveblog_settings: window.liveblog_settings,
				dispatchEvent: window.dispatchEvent,
			};

			// Provider SDKs are unavailable until explicitly loaded by each test.
			window.FB = undefined;
			window.twttr = undefined;
			window.instgrm = undefined;

			// Expose SDK URLs the way the server-side localisation does.
			window.liveblog_settings = { embed_sdks: sdkUrls };

			// Create a mock DOM element
			mockElement = document.createElement( 'div' );

			// Mock dispatchEvent
			window.dispatchEvent = vi.fn();
		} );

		afterEach( () => {
			// Restore original window properties
			window.FB = originalWindow.FB;
			window.twttr = originalWindow.twttr;
			window.instgrm = originalWindow.instgrm;
			window.liveblog_settings = originalWindow.liveblog_settings;
			window.dispatchEvent = originalWindow.dispatchEvent;

			// Remove any SDK scripts injected during a test so state does not leak.
			[ 'facebook', 'twitter', 'instagram', 'reddit' ].forEach(
				( name ) => {
					const script = injectedScript( name );
					if ( script ) {
						script.remove();
					}
				}
			);
		} );

		it( 'should call FB.XFBML.parse with the element when the SDK is loaded and markup is present', () => {
			const mockParse = vi.fn();
			window.FB = {
				XFBML: {
					parse: mockParse,
				},
			};

			mockElement.innerHTML =
				'<div class="fb-post" data-href="https://facebook.com/test"></div>';

			triggerOembedLoad( mockElement );

			expect( mockParse ).toHaveBeenCalledTimes( 1 );
			expect( mockParse ).toHaveBeenCalledWith( mockElement );
		} );

		it( 'should not process any provider when no embed markup is present', () => {
			const mockParse = vi.fn();
			window.FB = { XFBML: { parse: mockParse } };
			window.twttr = { widgets: { load: vi.fn() } };
			window.instgrm = { Embeds: { process: vi.fn() } };

			triggerOembedLoad( mockElement );

			expect( mockParse ).not.toHaveBeenCalled();
			expect( window.twttr.widgets.load ).not.toHaveBeenCalled();
			expect( window.instgrm.Embeds.process ).not.toHaveBeenCalled();
		} );

		it( 'should not throw when an SDK is not available', () => {
			window.FB = undefined;
			mockElement.innerHTML = '<div class="fb-post"></div>';

			expect( () => triggerOembedLoad( mockElement ) ).not.toThrow();
		} );

		it( 'should handle elements with fb:post (legacy XFBML format)', () => {
			const mockParse = vi.fn();
			window.FB = {
				XFBML: {
					parse: mockParse,
				},
			};

			// Add a legacy XFBML Facebook embed
			mockElement.innerHTML =
				'<fb:post href="https://facebook.com/test" data-width="552"></fb:post>';

			triggerOembedLoad( mockElement );

			// Should still call parse - the SDK handles both formats
			expect( mockParse ).toHaveBeenCalledWith( mockElement );
		} );

		it( 'should dispatch omembedTrigger custom event', () => {
			triggerOembedLoad( mockElement );

			expect( window.dispatchEvent ).toHaveBeenCalledTimes( 1 );
			expect( window.dispatchEvent ).toHaveBeenCalledWith(
				expect.any( CustomEvent )
			);
		} );

		it( 'should call Twitter widgets.load when Twitter SDK is available', () => {
			const mockLoad = vi.fn();
			window.twttr = {
				widgets: {
					load: mockLoad,
				},
			};

			// Add a Twitter embed
			mockElement.innerHTML =
				'<blockquote class="twitter-tweet"></blockquote>';

			triggerOembedLoad( mockElement );

			expect( mockLoad ).toHaveBeenCalled();
		} );

		it( 'should call Instagram Embeds.process when Instagram SDK is available', () => {
			const mockProcess = vi.fn();
			window.instgrm = {
				Embeds: {
					process: mockProcess,
				},
			};

			// Add an Instagram embed
			mockElement.innerHTML =
				'<blockquote class="instagram-media"></blockquote>';

			triggerOembedLoad( mockElement );

			expect( mockProcess ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should inject a provider SDK on demand when markup is present but the SDK is not loaded', () => {
			mockElement.innerHTML =
				'<blockquote class="twitter-tweet"></blockquote>';

			expect( injectedScript( 'twitter' ) ).toBeNull();

			triggerOembedLoad( mockElement );

			const script = injectedScript( 'twitter' );
			expect( script ).not.toBeNull();
			expect( script.src ).toBe( sdkUrls.twitter );
			expect( script.async ).toBe( true );
		} );

		it( 'should not inject a provider SDK when its markup is absent', () => {
			mockElement.innerHTML =
				'<blockquote class="twitter-tweet"></blockquote>';

			triggerOembedLoad( mockElement );

			// Only Twitter markup is present, so other SDKs must not be injected.
			expect( injectedScript( 'facebook' ) ).toBeNull();
			expect( injectedScript( 'instagram' ) ).toBeNull();
			expect( injectedScript( 'reddit' ) ).toBeNull();
			expect( injectedScript( 'twitter' ) ).not.toBeNull();
		} );

		it( 'should inject each SDK only once across multiple entries', () => {
			mockElement.innerHTML =
				'<blockquote class="twitter-tweet"></blockquote>';
			triggerOembedLoad( mockElement );

			const anotherEntry = document.createElement( 'div' );
			anotherEntry.innerHTML =
				'<blockquote class="twitter-tweet"></blockquote>';
			triggerOembedLoad( anotherEntry );

			expect( document.querySelectorAll( '#twitter-js' ).length ).toBe(
				1
			);
		} );

		it( 'should inject the Reddit SDK on demand for Reddit embeds', () => {
			mockElement.innerHTML =
				'<blockquote class="reddit-embed"></blockquote>';

			triggerOembedLoad( mockElement );

			expect( injectedScript( 'reddit' ) ).not.toBeNull();
		} );

		it( 'should not inject any SDK when no SDK URLs are configured', () => {
			window.liveblog_settings = { embed_sdks: {} };
			mockElement.innerHTML =
				'<blockquote class="twitter-tweet"></blockquote>';

			triggerOembedLoad( mockElement );

			expect( injectedScript( 'twitter' ) ).toBeNull();
		} );
	} );
} );

describe( 'key command helpers', () => {
	it( 'should find the /key command', () => {
		expect( hasKeyCommand( '/key Goal!' ) ).toBe( true );
		expect( hasKeyCommand( '<p>/key Goal!</p>' ) ).toBe( true );
		expect( hasKeyCommand( '<p>Goal! /key</p>' ) ).toBe( true );
		expect( hasKeyCommand( '<p>Goal!</p>' ) ).toBe( false );
		expect( hasKeyCommand( '<p>/keyboard</p>' ) ).toBe( false );
		expect(
			hasKeyCommand( '<a href="https://example.com/key">x</a>' )
		).toBe( false );
		expect( hasKeyCommand( undefined ) ).toBe( false );
	} );

	it( 'should strip the /key command', () => {
		expect( stripKeyCommand( '/key Goal!' ) ).toBe( 'Goal!' );
		expect( stripKeyCommand( '<p>/key Goal!</p>' ) ).toBe( '<p>Goal!</p>' );
		expect( stripKeyCommand( '<p>Goal! /key</p>' ) ).toBe(
			'<p>Goal! </p>'
		);
		expect( stripKeyCommand( '<p>/key</p><p>Goal!</p>' ) ).toBe(
			'<p>Goal!</p>'
		);
		expect(
			stripKeyCommand(
				'<p dir="ltr"><span style="white-space: pre-wrap;">/key</span></p><p>Goal!</p>'
			)
		).toBe( '<p>Goal!</p>' );
		expect( stripKeyCommand( '<p>/keyboard</p>' ) ).toBe(
			'<p>/keyboard</p>'
		);
	} );

	it( 'should keep blank paragraphs that were already there', () => {
		const content = '<p>/key Goal!</p><p><br></p><p>More</p>';
		expect( stripKeyCommand( content ) ).toBe(
			'<p>Goal!</p><p><br></p><p>More</p>'
		);
		expect( stripKeyCommand( '<p>One</p><p><br></p><p>Two</p>' ) ).toBe(
			'<p>One</p><p><br></p><p>Two</p>'
		);
	} );

	it( 'should ignore /key inside tag attributes', () => {
		const content = '<p><img src="a.jpg" alt="Goal /key moment"></p>';
		expect( hasKeyCommand( content ) ).toBe( false );
		expect( stripKeyCommand( content ) ).toBe( content );
	} );

	it( 'should find /key in plain text that has a > after it', () => {
		expect( hasKeyCommand( '/key 2 > 1' ) ).toBe( true );
		expect( stripKeyCommand( '/key 2 > 1' ) ).toBe( '2 > 1' );
	} );

	it( 'should only treat <p> as the first paragraph', () => {
		expect( addKeyCommand( '<pre>x</pre>' ) ).toBe( '/key <pre>x</pre>' );
	} );

	it( 'should add the /key command to the first paragraph', () => {
		expect( addKeyCommand( '<p dir="ltr">Goal!</p><p>More</p>' ) ).toBe(
			'<p dir="ltr">/key Goal!</p><p>More</p>'
		);
		expect( addKeyCommand( 'Goal!' ) ).toBe( '/key Goal!' );
		expect( addKeyCommand( '<h2>Goal!</h2>' ) ).toBe(
			'/key <h2>Goal!</h2>'
		);
	} );

	it( 'should not add the /key command twice', () => {
		expect( addKeyCommand( '<p>/key Goal!</p>' ) ).toBe(
			'<p>/key Goal!</p>'
		);
	} );

	it( 'should round trip through strip and add', () => {
		const content = '<p>/key Goal!</p>';
		expect( addKeyCommand( stripKeyCommand( content ) ) ).toBe( content );
	} );
} );
