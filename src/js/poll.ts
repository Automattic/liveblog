interface PollerOptions {
	pollingInterval?: number;
	pollingUrl?: string;
}

class LiveblogPoller {
	private postId: number;
	private container: HTMLElement;
	private lastTimestamp: number;
	private pollingInterval: number;
	private pollingUrl: string;
	private timerId: number | null = null;
	private isActive = false;
	private isFetching = false;
	private pendingEntries: HTMLElement[] = [];
	private pendingCount = 0;
	private firstEntry: Element | null = null;
	private updatesButton: HTMLButtonElement;

	constructor(
		postId: number,
		container: HTMLElement,
		options: PollerOptions = {}
	) {
		this.postId = postId;
		this.container = container;
		this.lastTimestamp =
			parseInt( container.dataset.lastTimestamp ?? '', 10 ) || 0;
		this.pollingInterval = options.pollingInterval || 10000;
		this.pollingUrl =
			options.pollingUrl ||
			`/wp-json/liveblog/v1/${ postId }/entries-html`;

		this.updatesButton = document.createElement( 'button' );
		this.updatesButton.className = 'liveblog-new-updates';
		this.updatesButton.style.display = 'none';
		document.body.appendChild( this.updatesButton );

		this.updatesButton.addEventListener( 'click', () => {
			this.injectPendingEntries();
		} );
	}

	start(): void {
		if ( this.isActive ) {
			return;
		}

		this.isActive = true;
		this.firstEntry = this.container.querySelector( '.liveblog-entry' );
		this.scheduleNext();
	}

	stop(): void {
		this.isActive = false;
		if ( this.timerId ) {
			clearTimeout( this.timerId );
			this.timerId = null;
		}
	}

	destroy(): void {
		this.stop();
		if ( this.updatesButton && this.updatesButton.parentNode ) {
			this.updatesButton.parentNode.removeChild( this.updatesButton );
		}
		this.pendingEntries = [];
		this.pendingCount = 0;
	}

	scheduleNext(): void {
		if ( ! this.isActive ) {
			return;
		}

		this.timerId = window.setTimeout( () => {
			this.fetchNewEntries().finally( () => this.scheduleNext() );
		}, this.pollingInterval );
	}

	isUserScrolledPastFirst(): boolean {
		if ( ! this.firstEntry ) {
			return false;
		}

		const rect = this.firstEntry.getBoundingClientRect();
		return rect.bottom < 0;
	}

	async fetchNewEntries(): Promise< void > {
		if ( this.isFetching ) {
			return;
		}

		this.isFetching = true;

		const url = `${ this.pollingUrl }/${ this.lastTimestamp }`;

		try {
			const response = await fetch( url, { method: 'GET' } );

			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}

			const data = await response.json();

			if ( data.timestamp && data.timestamp > this.lastTimestamp ) {
				this.lastTimestamp = data.timestamp;
			}

			if ( data.html && data.html.trim() ) {
				const temp = document.createElement( 'div' );
				temp.innerHTML = data.html;
				const entries =
					temp.querySelectorAll< HTMLElement >( '.liveblog-entry' );

				if ( entries.length === 0 ) {
					return;
				}

				const updates: HTMLElement[] = [];
				const newcomers: HTMLElement[] = [];

				entries.forEach( ( entry ) => {
					const entryId =
						entry.dataset?.entryId ||
						entry.getAttribute( 'data-entry-id' );
					if (
						entryId &&
						this.container.querySelector(
							`[data-entry-id="${ entryId }"]`
						)
					) {
						updates.push( entry );
					} else {
						newcomers.push( entry );
					}
				} );

				if ( updates.length > 0 ) {
					this.injectNow( updates );
				}

				if ( newcomers.length > 0 ) {
					if ( this.isUserScrolledPastFirst() ) {
						this.queuePending( newcomers );
					} else {
						this.injectNow( newcomers );
					}
				}
			}
		} catch ( error ) {
			const message =
				error instanceof Error ? error.message : String( error );
			document.dispatchEvent(
				new CustomEvent( 'liveblog:error', {
					detail: { error: message },
				} )
			);
			if ( window.console && window.console.error ) {
				window.console.error( 'LiveblogPoller error:', error );
			}
		} finally {
			this.isFetching = false;
		}
	}

	queuePending( entries: HTMLElement[] ): void {
		const entriesArr = Array.from( entries );
		this.pendingEntries.push( ...entriesArr );
		this.pendingCount += entriesArr.length;
		this.updatesButton.textContent = `${ this.pendingCount } new update${
			this.pendingCount > 1 ? 's' : ''
		}`;
		this.updatesButton.style.display = 'block';
	}

	injectPendingEntries(): void {
		const all = [ ...this.pendingEntries ];
		this.pendingEntries = [];
		this.pendingCount = 0;
		this.updatesButton.style.display = 'none';

		this.container.scrollIntoView( { behavior: 'smooth', block: 'start' } );

		setTimeout( () => {
			this.injectNow( all.reverse() );
		}, 400 );
	}

	injectNow( entries: HTMLElement[] ): void {
		const reversed = Array.from( entries ).reverse();
		const fragment = document.createDocumentFragment();

		reversed.forEach( ( entry ) => {
			const entryId = entry.dataset
				? entry.dataset.entryId
				: entry.getAttribute( 'data-entry-id' );
			if ( ! entryId ) {
				return;
			}

			const existing = this.container.querySelector(
				`[data-entry-id="${ entryId }"]`
			);
			if ( existing ) {
				existing.replaceWith( entry );
			} else {
				fragment.appendChild( entry );
			}
		} );

		if ( fragment.childNodes.length > 0 ) {
			const emptyMsg = this.container.querySelector( '.liveblog-empty' );
			if ( emptyMsg ) {
				emptyMsg.remove();
			}

			this.container.insertBefore( fragment, this.container.firstChild );
		}

		if ( reversed.length > 0 ) {
			const newest = reversed[ reversed.length - 1 ];
			const ts =
				parseInt(
					newest.dataset.timestamp ||
						newest.getAttribute( 'data-timestamp' ) ||
						'',
					10
				) || 0;
			if ( ts > this.lastTimestamp ) {
				this.lastTimestamp = ts;
			}
		}

		this.firstEntry = this.container.querySelector( '.liveblog-entry' );

		document.dispatchEvent(
			new CustomEvent( 'liveblog:new-entries', {
				detail: { count: reversed.length },
			} )
		);
	}
}

export default LiveblogPoller;

export function initLiveblogPoller(): LiveblogPoller | null {
	const container = document.getElementById( 'liveblog-entries' );
	if ( ! container ) {
		return null;
	}

	const postId = parseInt( container.dataset.postId ?? '', 10 );
	if ( ! postId ) {
		return null;
	}

	const pollingInterval =
		window.liveblogPollingConfig?.polling_interval || 10;

	const poller = new LiveblogPoller( postId, container, {
		pollingInterval: pollingInterval * 1000,
	} );

	if ( ! container.dataset.isArchived ) {
		poller.start();
	}

	window.liveblogPoller = poller;

	return poller;
}
