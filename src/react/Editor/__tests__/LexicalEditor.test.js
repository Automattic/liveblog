/**
 * Behavioural tests for LexicalEditor, mounted for real in jsdom.
 *
 * The editor has no E2E coverage, so these are the safety net for Lexical
 * upgrades: they drive the real component through Lexical's own APIs and
 * assert on the HTML that would be saved as the entry content.
 */

/* global Range, DOMRect, DOMParser, MouseEvent, HTMLInputElement */

import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	$createLineBreakNode,
	$createParagraphNode,
	$createTextNode,
	$getRoot,
	$selectAll,
	DROP_COMMAND,
	KEY_ARROW_DOWN_COMMAND,
	KEY_ENTER_COMMAND,
	KEY_ESCAPE_COMMAND,
	UNDO_COMMAND,
	getNearestEditorFromDOMNode,
} from 'lexical';

import LexicalEditor from '../LexicalEditor';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

// jsdom has no layout; Lexical and the autocomplete menu measure ranges.
Range.prototype.getBoundingClientRect = () => new DOMRect();
Range.prototype.getClientRects = () => [];

let container;
let root;
let consoleError;

beforeEach( () => {
	consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => {} );
} );

afterEach( async () => {
	await act( async () => root?.unmount() );
	container?.remove();
	root = null;
	container = null;
	consoleError.mockRestore();
} );

async function renderEditor( props = {} ) {
	const onChange = vi.fn();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );

	const render = ( extra = {} ) =>
		act( async () => {
			root.render(
				<LexicalEditor
					onChange={ onChange }
					{ ...props }
					{ ...extra }
				/>
			);
		} );
	await render();

	const editable = container.querySelector( '[contenteditable]' );
	return {
		editor: getNearestEditorFromDOMNode( editable ),
		editable,
		onChange,
		rerender: render,
		html: () => onChange.mock.lastCall?.[ 0 ],
	};
}

const run = ( fn ) => act( async () => fn() );

const update = ( editor, fn ) =>
	run( () => editor.update( fn, { discrete: true } ) );

const button = ( label ) =>
	container.querySelector( `button[aria-label="${ label }"]` );

const click = ( el ) => run( () => el.click() );

/**
 * Replace the content with a single paragraph and put the caret at its end.
 *
 * @param {Object} editor Lexical editor.
 * @param {string} text   Paragraph text.
 */
const typeParagraph = ( editor, text ) =>
	update( editor, () => {
		const textNode = $createTextNode( text );
		$getRoot().clear().append( $createParagraphNode().append( textNode ) );
		textNode.select();
	} );

const selectAll = ( editor ) => update( editor, () => $selectAll() );

const lexicalErrors = () =>
	consoleError.mock.calls.filter( ( args ) =>
		String( args[ 0 ] ).includes( 'Lexical' )
	);

describe( 'LexicalEditor', () => {
	describe( 'mounting', () => {
		it( 'renders the editor, toolbar and placeholder without errors', async () => {
			const { editor, editable } = await renderEditor();

			expect( editor ).toBeTruthy();
			expect( editable.getAttribute( 'contenteditable' ) ).toBe( 'true' );
			expect( editable.getAttribute( 'aria-label' ) ).toBe(
				'Liveblog entry content'
			);
			expect( button( 'Bold (Ctrl+B)' ) ).toBeTruthy();
			expect(
				container.querySelector( '.liveblog-lexical-placeholder' )
			).toBeTruthy();
			expect( consoleError ).not.toHaveBeenCalled();
		} );

		it( 'is not editable and disables the toolbar when read-only', async () => {
			const { editable } = await renderEditor( { readOnly: true } );

			expect( editable.getAttribute( 'contenteditable' ) ).toBe(
				'false'
			);
			expect( button( 'Bold (Ctrl+B)' ).disabled ).toBe( true );
			expect( button( 'Blockquote' ).disabled ).toBe( true );
		} );

		it( 'only shows the image button when uploads are supported', async () => {
			await renderEditor();
			expect( button( 'Insert Image' ) ).toBeNull();
		} );
	} );

	describe( 'HTML import and export round trip', () => {
		it.each( [
			[ 'paragraph', '<p>Plain text</p>', '<p>Plain text</p>' ],
			[
				'inline formats',
				'<p><strong>Bold</strong> <em>italic</em> <u>under</u></p>',
				'<p><strong>Bold</strong> <em>italic</em> <u>under</u></p>',
			],
			[ 'heading', '<h2>Breaking</h2>', '<h2>Breaking</h2>' ],
			[
				'unordered list',
				'<ul><li>One</li><li>Two</li></ul>',
				'<ul><li value="1">One</li><li value="2">Two</li></ul>',
			],
			[
				'ordered list',
				'<ol><li>One</li><li>Two</li></ol>',
				'<ol><li value="1">One</li><li value="2">Two</li></ol>',
			],
			[
				'blockquote',
				'<blockquote>Quoted</blockquote>',
				'<blockquote>Quoted</blockquote>',
			],
			[
				'link',
				'<p><a href="https://example.com/">Link</a></p>',
				'<p><a href="https://example.com/">Link</a></p>',
			],
			[
				'line break',
				'<p>Line one<br>Line two</p>',
				'<p>Line one<br>Line two</p>',
			],
			[
				'liveblog command syntax kept as text',
				'<p>Hi @gary #news :smile: /key</p>',
				'<p>Hi @gary #news :smile: /key</p>',
			],
		] )( '%s', async ( _name, input, expected ) => {
			const { html } = await renderEditor( { initialContent: input } );

			expect( html() ).toBe( expected );
			expect( lexicalErrors() ).toEqual( [] );
		} );

		it( 'renders imported content with the theme classes', async () => {
			const { editable } = await renderEditor( {
				initialContent: '<h2>Head</h2><blockquote>Q</blockquote>',
			} );

			expect( editable.querySelector( 'h2' ).className ).toBe(
				'liveblog-lexical-h2'
			);
			expect( editable.querySelector( 'blockquote' ).className ).toBe(
				'liveblog-lexical-quote'
			);
		} );

		it( 'neutralises javascript: links', async () => {
			const { html } = await renderEditor( {
				initialContent: '<p><a href="javascript:alert(1)">x</a></p>',
			} );

			expect( html() ).not.toContain( 'javascript:' );
		} );

		it( 'does not leak Lexical-only markup into saved HTML', async () => {
			const { html } = await renderEditor( {
				initialContent:
					'<p><strong>a</strong><br></p><ul><li>b</li></ul><p><img src="https://example.com/i.jpg" alt=""></p>',
			} );

			expect( html() ).not.toMatch(
				/liveblog-lexical|white-space|data-lexical|<span/
			);
		} );
		it( 'saves a trailing line break without the Lexical marker br', async () => {
			const { editor, html } = await renderEditor();
			await update( editor, () => {
				$getRoot()
					.clear()
					.append(
						$createParagraphNode().append(
							$createTextNode( 'Last line' ),
							$createLineBreakNode()
						)
					);
			} );

			expect( html() ).toBe( '<p>Last line<br></p>' );
		} );
	} );

	describe( 'image node', () => {
		const IMG =
			'<img src="https://example.com/a.jpg" alt="A cat" width="300" height="200" class="wp-image-5 size-medium" srcset="https://example.com/a-300.jpg 300w" data-id="5">';

		it( 'preserves every img attribute through import and export', async () => {
			const { html } = await renderEditor( {
				initialContent: `<p>${ IMG }</p>`,
			} );

			const img = new DOMParser()
				.parseFromString( html(), 'text/html' )
				.querySelector( 'img' );
			expect( img ).toBeTruthy();
			expect(
				Object.fromEntries(
					[ ...img.attributes ].map( ( a ) => [ a.name, a.value ] )
				)
			).toEqual( {
				src: 'https://example.com/a.jpg',
				alt: 'A cat',
				width: '300',
				height: '200',
				class: 'wp-image-5 size-medium',
				srcset: 'https://example.com/a-300.jpg 300w',
				'data-id': '5',
			} );
		} );

		it( 'exports a bare img without the editor wrapper span', async () => {
			const { html } = await renderEditor( {
				initialContent:
					'<p><img src="https://example.com/a.jpg" alt="x"></p>',
			} );

			expect( html() ).toBe(
				'<p><img src="https://example.com/a.jpg" alt="x"></p>'
			);
		} );

		it( 'ignores img elements without a src', async () => {
			const { html } = await renderEditor( {
				initialContent: '<p>Text<img alt="nothing"></p>',
			} );

			expect( html() ).toBe( '<p>Text</p>' );
		} );

		it( 'renders through the decorator with the stored dimensions', async () => {
			const { editable } = await renderEditor( {
				initialContent: `<p>${ IMG }</p>`,
			} );

			const wrapper = editable.querySelector(
				'.liveblog-lexical-image-wrapper'
			);
			expect( wrapper ).toBeTruthy();
			const img = wrapper.querySelector( 'img.liveblog-lexical-image' );
			expect( img.getAttribute( 'src' ) ).toBe(
				'https://example.com/a.jpg'
			);
			expect( img.getAttribute( 'alt' ) ).toBe( 'A cat' );
			expect( img.style.width ).toBe( '300px' );
			expect( img.style.height ).toBe( '200px' );
		} );

		it( 'round-trips through Lexical JSON serialisation', async () => {
			const { editor } = await renderEditor( {
				initialContent: `<p>${ IMG }</p>`,
			} );

			const json = JSON.stringify( editor.getEditorState().toJSON() );
			const restored = editor.parseEditorState( json );
			expect( JSON.stringify( restored.toJSON() ) ).toBe( json );
			expect( json ).toContain( '"type":"image"' );
		} );

		it( 'shows resize handles when clicked and resizes keeping aspect ratio', async () => {
			const { editable, html } = await renderEditor( {
				initialContent:
					'<p><img src="https://example.com/a.jpg" alt="" width="200" height="100"></p>',
			} );
			const img = editable.querySelector( 'img.liveblog-lexical-image' );
			Object.defineProperty( img, 'offsetWidth', { value: 200 } );
			Object.defineProperty( img, 'offsetHeight', { value: 100 } );

			await click( img );

			const handle = editable.querySelector( '.liveblog-resize-handle' );
			expect( handle ).toBeTruthy();
			expect(
				editable.querySelector( '.liveblog-resizable-image-container' )
					.classList
			).toContain( 'selected' );

			await run( () =>
				handle.dispatchEvent(
					new MouseEvent( 'mousedown', { bubbles: true, clientX: 0 } )
				)
			);
			await run( () =>
				document.dispatchEvent(
					new MouseEvent( 'mousemove', { clientX: 100 } )
				)
			);
			await run( () =>
				document.dispatchEvent( new MouseEvent( 'mouseup' ) )
			);

			expect( html() ).toContain( 'width="300"' );
			expect( html() ).toContain( 'height="150"' );
		} );
	} );

	describe( 'toolbar', () => {
		it.each( [
			[ 'Bold (Ctrl+B)', '<p><strong>Hello</strong></p>' ],
			[ 'Italic (Ctrl+I)', '<p><em>Hello</em></p>' ],
			[ 'Underline (Ctrl+U)', '<p><u>Hello</u></p>' ],
		] )( '%s formats the selection', async ( label, expected ) => {
			const { editor, html } = await renderEditor();
			await typeParagraph( editor, 'Hello' );
			await selectAll( editor );

			await click( button( label ) );

			expect( html() ).toBe( expected );
		} );

		it( 'marks the bold button active when the caret is in bold text', async () => {
			const { editor } = await renderEditor( {
				initialContent: '<p><strong>Bold</strong></p>',
			} );

			await selectAll( editor );

			expect( button( 'Bold (Ctrl+B)' ).className ).toContain(
				'is-active'
			);
			expect( button( 'Italic (Ctrl+I)' ).className ).not.toContain(
				'is-active'
			);
		} );

		it.each( [
			[ 'Unordered List', 'ul' ],
			[ 'Ordered List', 'ol' ],
		] )( '%s toggles a list on and off', async ( label, tag ) => {
			const { editor, html } = await renderEditor();
			await typeParagraph( editor, 'Item' );

			await click( button( label ) );
			expect( html() ).toBe(
				`<${ tag }><li value="1">Item</li></${ tag }>`
			);
			expect( button( label ).className ).toContain( 'is-active' );

			await click( button( label ) );
			expect( html() ).toBe( '<p>Item</p>' );
		} );

		it( 'toggles a blockquote on and off', async () => {
			const { editor, html } = await renderEditor();
			await typeParagraph( editor, 'Said' );

			await click( button( 'Blockquote' ) );
			expect( html() ).toBe( '<blockquote>Said</blockquote>' );
			expect( button( 'Blockquote' ).className ).toContain( 'is-active' );

			await click( button( 'Blockquote' ) );
			expect( html() ).toBe( '<p>Said</p>' );
		} );

		it( 'adds and removes a link', async () => {
			const { editor, html } = await renderEditor();
			await typeParagraph( editor, 'WordPress' );
			await selectAll( editor );

			await click( button( 'Add Link' ) );
			const input = container.querySelector( 'input[type="url"]' );
			expect( input.value ).toBe( 'https://' );

			await run( () => {
				Object.getOwnPropertyDescriptor(
					HTMLInputElement.prototype,
					'value'
				).set.call( input, 'https://wordpress.org/' );
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );
			await click( container.querySelector( '.liveblog-input-enter' ) );

			expect( html() ).toBe(
				'<p><a href="https://wordpress.org/" rel="noreferrer">WordPress</a></p>'
			);
			expect( container.querySelector( 'input[type="url"]' ) ).toBeNull();

			await selectAll( editor );
			expect( button( 'Add Link' ).className ).toContain( 'is-active' );
			expect( button( 'Remove Link' ).disabled ).toBe( false );

			await click( button( 'Remove Link' ) );
			expect( html() ).toBe( '<p>WordPress</p>' );
		} );

		it( 'does not open the link input for a collapsed selection', async () => {
			const { editor } = await renderEditor();
			await typeParagraph( editor, 'Text' );

			await click( button( 'Add Link' ) );

			expect( container.querySelector( 'input[type="url"]' ) ).toBeNull();
		} );

		it( 'undoes formatting via the history plugin', async () => {
			const { editor, html } = await renderEditor();
			await typeParagraph( editor, 'Hello' );
			await selectAll( editor );
			await click( button( 'Bold (Ctrl+B)' ) );
			expect( html() ).toBe( '<p><strong>Hello</strong></p>' );

			await run( () =>
				editor.dispatchCommand( UNDO_COMMAND, undefined )
			);

			expect( html() ).toBe( '<p>Hello</p>' );
		} );
	} );

	describe( 'image upload', () => {
		const file = ( name, type ) => new File( [ 'x' ], name, { type } );

		const drop = async ( editor, files ) => {
			const event = {
				dataTransfer: { files, types: [ 'Files' ] },
				preventDefault: vi.fn(),
			};
			let handled;
			await run( async () => {
				handled = editor.dispatchCommand( DROP_COMMAND, event );
				// Let the sequential upload chain settle.
				await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
			} );
			return { handled, event };
		};

		it( 'uploads dropped images in order and skips other files', async () => {
			const handleImageUpload = vi.fn( ( f ) =>
				Promise.resolve( `https://example.com/${ f.name }` )
			);
			const { editor, html } = await renderEditor( {
				handleImageUpload,
			} );
			await typeParagraph( editor, 'Pics:' );

			const { handled, event } = await drop( editor, [
				file( 'one.png', 'image/png' ),
				file( 'notes.txt', 'text/plain' ),
				file( 'two.jpg', 'image/jpeg' ),
			] );

			expect( handled ).toBe( true );
			expect( event.preventDefault ).toHaveBeenCalled();
			expect(
				handleImageUpload.mock.calls.map( ( [ f ] ) => f.name )
			).toEqual( [ 'one.png', 'two.jpg' ] );
			expect( html() ).toBe(
				'<p>Pics:<img src="https://example.com/one.png" alt="one.png"><img src="https://example.com/two.jpg" alt="two.jpg"></p>'
			);
		} );

		it( 'carries on after a failed upload', async () => {
			const handleImageUpload = vi.fn( ( f ) =>
				f.name === 'bad.png'
					? Promise.reject( new Error( 'nope' ) )
					: Promise.resolve( `https://example.com/${ f.name }` )
			);
			const { editor, html } = await renderEditor( {
				handleImageUpload,
			} );
			await typeParagraph( editor, '' );

			await drop( editor, [
				file( 'bad.png', 'image/png' ),
				file( 'good.png', 'image/png' ),
			] );

			expect( handleImageUpload ).toHaveBeenCalledTimes( 2 );
			expect( html() ).toContain( 'https://example.com/good.png' );
			expect( html() ).not.toContain( 'bad.png' );
		} );

		it( 'leaves non-image drops to Lexical', async () => {
			const handleImageUpload = vi.fn();
			const { editor } = await renderEditor( { handleImageUpload } );

			const { handled } = await drop( editor, [
				file( 'notes.txt', 'text/plain' ),
			] );

			expect( handled ).toBe( false );
			expect( handleImageUpload ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'autocomplete', () => {
		const enter = ( editor ) =>
			run( () =>
				editor.dispatchCommand( KEY_ENTER_COMMAND, {
					preventDefault: vi.fn(),
				} )
			);

		it.each( [
			[ '@', 'ga', [ { key: 'gary', name: 'Gary' } ], 'Hi @gary ' ],
			[ '#', 'ne', [ { name: 'news' } ], 'Hi #news ' ],
			[ ':', 'smi', [ { key: 'smile', image: '1f604' } ], 'Hi :smile: ' ],
			[ '/', 'ke', [ '/key' ], 'Hi /key ' ],
		] )(
			'%s trigger searches and inserts the chosen suggestion',
			async ( trigger, query, suggestions, expected ) => {
				const onSearch = vi.fn();
				const { editor, rerender, html } = await renderEditor( {
					onSearch,
				} );

				await typeParagraph( editor, `Hi ${ trigger }${ query }` );
				expect( onSearch ).toHaveBeenLastCalledWith( trigger, query );

				await rerender( { onSearch, suggestions } );
				expect(
					container.querySelector( '.liveblog-autocomplete-menu' )
				).toBeTruthy();

				await enter( editor );

				expect( html() ).toBe( `<p>${ expected }</p>` );
			}
		);

		it( 'does not trigger inside a word such as an email address', async () => {
			const onSearch = vi.fn();
			const { editor } = await renderEditor( { onSearch } );

			await typeParagraph( editor, 'mail me@example' );

			expect( onSearch ).not.toHaveBeenCalled();
		} );

		it( 'moves the highlight with arrow keys and closes on escape', async () => {
			const onSearch = vi.fn();
			const suggestions = [ { name: 'Alpha' }, { name: 'Beta' } ];
			const { editor, rerender } = await renderEditor( { onSearch } );
			await typeParagraph( editor, '@a' );
			await rerender( { onSearch, suggestions } );

			const selected = () =>
				container.querySelector(
					'.liveblog-autocomplete-item.is-selected'
				).textContent;
			expect( selected() ).toBe( 'Alpha' );

			await run( () =>
				editor.dispatchCommand( KEY_ARROW_DOWN_COMMAND, {
					preventDefault: vi.fn(),
				} )
			);
			expect( selected() ).toBe( 'Beta' );

			await run( () =>
				editor.dispatchCommand( KEY_ESCAPE_COMMAND, {
					preventDefault: vi.fn(),
				} )
			);
			expect(
				container.querySelector( '.liveblog-autocomplete-menu' )
			).toBeNull();
		} );
	} );
} );
