import { mountEntriesDataView } from './entries-view';
import type { LiveblogAdminSettings, ToggleStateResponse } from '../types';

export default class LiveblogAdmin {
	private endpointUrl: string;
	private nonceKey: string;
	private nonce: string;
	private errorTemplate: string;
	private shortErrorTemplate: string;
	private metaBox?: HTMLElement | null;
	private postId?: HTMLInputElement | null;
	private errorEl?: HTMLElement | null;
	private inside?: HTMLElement | null;

	constructor( settings?: LiveblogAdminSettings ) {
		this.endpointUrl = settings?.endpoint_url || '';
		this.nonceKey = settings?.nonce_key || '';
		this.nonce = settings?.nonce || '';
		this.errorTemplate = settings?.error_message_template || '';
		this.shortErrorTemplate = settings?.short_error_message_template || '';

		this.init();
	}

	init(): this {
		this.metaBox = document.getElementById( 'liveblog' );
		if ( ! this.metaBox ) {
			return this;
		}

		this.postId = document.getElementById(
			'post_ID'
		) as HTMLInputElement | null;
		if ( ! this.postId || ! this.postId.value ) {
			return this;
		}

		this.errorEl = this.metaBox.querySelector( 'p.error' );
		this.inside = this.metaBox.querySelector( '.inside' );

		this.bindEvents();
		this.mountEntriesView();

		return this;
	}

	bindEvents(): void {
		this.metaBox!.addEventListener( 'click', ( event ) => {
			const button = ( event.target as HTMLElement ).closest(
				'button'
			) as HTMLButtonElement | null;
			if ( button && button.closest( '.liveblog-state-buttons' ) ) {
				event.preventDefault();
				this.toggleState( button );
			}
		} );
	}

	mountEntriesView(): void {
		mountEntriesDataView();
	}

	showError( status: string, code: string ): void {
		const template = code ? this.errorTemplate : this.shortErrorTemplate;
		const message = template
			.replace( '{error-message}', status )
			.replace( '{error-code}', code );
		if ( this.errorEl ) {
			this.errorEl.textContent = message;
			this.errorEl.style.display = '';
		}
	}

	hideError(): void {
		if ( this.errorEl ) {
			this.errorEl.style.display = 'none';
		}
	}

	async toggleState( button: HTMLButtonElement ): Promise< void > {
		this.hideError();

		const state = button.value;
		const data = new URLSearchParams();
		data.append( 'state', state );
		data.append( this.nonceKey, this.nonce );

		try {
			const response = await fetch( this.endpointUrl, {
				method: 'POST',
				body: data,
			} );

			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}

			const result: ToggleStateResponse = await response.json();
			const html = result.metabox_html || '';
			if ( this.inside ) {
				this.inside.innerHTML = html;
				this.errorEl = this.metaBox!.querySelector( 'p.error' );
			}

			this.mountEntriesView();
		} catch ( error ) {
			const message =
				error instanceof Error ? error.message : String( error );
			this.showError( message, '' );
		}
	}
}
