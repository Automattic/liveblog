import { describe, expect, it } from 'vitest';
import { getAuthorsAfterPublish, getInitialAuthors } from '../EditorContainer';

const currentUser = { id: 1, key: 'admin', name: 'Admin' };
const otherUser = { id: 2, key: 'editor', name: 'Editor' };

const prefillOn = { prefill_author_field: '1', current_user: currentUser };
const prefillOff = { prefill_author_field: '', current_user: currentUser };

describe( 'getInitialAuthors', () => {
	it( 'prefills the current user for a new entry by default', () => {
		expect( getInitialAuthors( undefined, prefillOn ) ).toEqual( [
			currentUser,
		] );
	} );

	it( 'starts empty for a new entry when prefill is off', () => {
		expect( getInitialAuthors( undefined, prefillOff ) ).toEqual( [] );
	} );

	it( 'uses the saved authors when editing, whatever the setting', () => {
		const entry = { authors: [ otherUser ] };
		expect( getInitialAuthors( entry, prefillOn ) ).toEqual( [
			otherUser,
		] );
		expect( getInitialAuthors( entry, prefillOff ) ).toEqual( [
			otherUser,
		] );
	} );
} );

describe( 'getAuthorsAfterPublish', () => {
	it( 'keeps the selected authors when prefill is on', () => {
		const authors = [ currentUser, otherUser ];
		expect( getAuthorsAfterPublish( authors, prefillOn ) ).toBe( authors );
	} );

	it( 'clears the authors when prefill is off', () => {
		expect(
			getAuthorsAfterPublish( [ currentUser, otherUser ], prefillOff )
		).toEqual( [] );
	} );
} );
