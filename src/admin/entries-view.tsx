import { createRoot, useState, useMemo, useCallback } from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal } from '@wordpress/components';
import type {
	EntryRecord,
	ViewState,
	FilteredData,
	EntriesDataViewProps,
	DataViewConfig,
	BreakoutResponse,
	BreakoutMessage,
} from '../types';

function TrashIcon(): JSX.Element {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="20"
			height="20"
			aria-hidden="true"
		>
			<path d="M6.187 8h11.625l-.695 11.125C17.05 20.18 16.177 21 15.12 21H8.88c-1.057 0-1.93-.82-1.997-1.875L6.187 8zM19 5v2H5V5h3V4c0-1.103.897-2 2-2h4c1.103 0 2 .897 2 2v1h3zm-9 0h4V4h-4v1z" />
		</svg>
	);
}

function PencilIcon(): JSX.Element {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="20"
			height="20"
			aria-hidden="true"
		>
			<path d="M20.1 5.1L16.9 2 6.2 12.7l-1.3 4.4 4.5-1.3L20.1 5.1zM4 20.8h8v-1.5H4v1.5z" />
		</svg>
	);
}

function BreakoutIcon(): JSX.Element {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="20"
			height="20"
			aria-hidden="true"
		>
			<path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z" />
		</svg>
	);
}

const fields = [
	{
		id: 'id',
		label: __( 'ID', 'liveblog' ),
		type: 'text' as const,
		render: ( { item }: { item: EntryRecord } ) => (
			<a href={ `post.php?post=${ item.id }&action=edit` }>
				#{ item.id }
			</a>
		),
	},
	{
		id: 'title',
		label: __( 'Title', 'liveblog' ),
		type: 'text' as const,
		enableGlobalSearch: true,
		render: ( { item }: { item: EntryRecord } ) => (
			<span style={ { display: 'flex', alignItems: 'center', gap: 8 } }>
				<span>{ item.title || __( '(no title)', 'liveblog' ) }</span>
			</span>
		),
	},
	{
		id: 'date',
		label: __( 'Date', 'liveblog' ),
		type: 'datetime' as const,
		render: ( { item }: { item: EntryRecord } ) => (
			<time>{ item.date }</time>
		),
	},
	{
		id: 'breakout',
		label: __( 'Breakout', 'liveblog' ),
		type: 'text' as const,
		render: ( { item }: { item: EntryRecord } ) => {
			if ( ! item.breakout_post_id ) {
				return <>—</>;
			}

			const isPublished = item.breakout_status === 'publish';
			const color = isPublished ? '#00a32a' : '#dba617';
			const label = isPublished
				? __( 'Published', 'liveblog' )
				: __( 'Draft', 'liveblog' );

			return (
				<a
					href={ `post.php?post=${ item.breakout_post_id }&action=edit` }
					style={ { fontWeight: 600, color } }
				>
					#{ item.breakout_post_id } { label }
				</a>
			);
		},
	},
];

export default function EntriesDataView( {
	entries,
	postId,
	isArchived,
}: EntriesDataViewProps ): JSX.Element {
	const [ data, setData ] = useState< EntryRecord[] >( entries );
	const [ deleteError, setDeleteError ] = useState< string | null >( null );
	const [ showDeleteModal, setShowDeleteModal ] = useState<
		EntryRecord[] | null
	>( null );
	const [ breakoutMessage, setBreakoutMessage ] =
		useState< BreakoutMessage | null >( null );

	const [ view, setView ] = useState< ViewState >( {
		type: 'table',
		perPage: 20,
		page: 1,
		search: '',
		sort: { field: 'date', direction: 'desc' },
		fields: [ 'id', 'title', 'breakout', 'date' ],
		layout: {
			density: 'compact',
			styles: { id: { width: 50, minWidth: 50 } },
		},
		titleField: '',
	} );

	const { data: filtered, paginationInfo }: FilteredData = useMemo(
		() => filterSortAndPaginate( data, view, fields ),
		[ data, view ]
	);

	const handleDelete = useCallback( async ( items: EntryRecord[] ) => {
		setDeleteError( null );
		const ids: number[] = [];
		let failed = 0;

		for ( const item of items ) {
			try {
				await apiFetch( {
					path: `/wp/v2/posts/${ item.id }?force=true`,
					method: 'DELETE',
				} );
				ids.push( item.id );
			} catch ( e ) {
				failed++;
			}
		}

		if ( failed > 0 ) {
			setDeleteError(
				sprintf(
					/* translators: %d: number of entries that failed to delete */
					__( '%d entry deletion(s) failed.', 'liveblog' ),
					failed
				)
			);
		}

		if ( ids.length ) {
			setData( ( prev ) =>
				prev.filter( ( e ) => ! ids.includes( e.id ) )
			);
		}
		setShowDeleteModal( null );
	}, [] );

	const handleBreakout = useCallback(
		async ( item: EntryRecord ) => {
			setBreakoutMessage( null );

			try {
				const result: BreakoutResponse = await apiFetch( {
					path: `/liveblog/v1/${ postId }/breakout/${ item.id }`,
					method: 'POST',
				} );

				setBreakoutMessage( {
					type: 'success',
					text: sprintf(
						/* translators: %d: breakout post ID */
						__( 'Breakout post #%d created as draft.', 'liveblog' ),
						result.breakout_post_id
					),
				} );

				setData( ( prev ) =>
					prev.map( ( e ) =>
						e.id === item.id
							? {
									...e,
									breakout_post_id: result.breakout_post_id,
									breakout_status: 'draft',
							  }
							: e
					)
				);
			} catch ( e ) {
				setBreakoutMessage( {
					type: 'error',
					text: __( 'Failed to create breakout post.', 'liveblog' ),
				} );
			} finally {
				// No cleanup needed
			}
		},
		[ postId ]
	);

	const actions = isArchived
		? []
		: [
				{
					id: 'edit',
					label: __( 'Edit', 'liveblog' ),
					icon: <PencilIcon />,
					isPrimary: true,
					supportsBulk: false,
					callback: ( items: EntryRecord[] ) => {
						window.location.href = `post.php?post=${ items[ 0 ].id }&action=edit`;
					},
				},
				{
					id: 'breakout',
					label: __( 'Breakout', 'liveblog' ),
					icon: <BreakoutIcon />,
					supportsBulk: false,
					callback: ( items: EntryRecord[] ) => {
						handleBreakout( items[ 0 ] );
					},
				},
				{
					id: 'delete',
					label: __( 'Delete', 'liveblog' ),
					icon: <TrashIcon />,
					supportsBulk: true,
					callback: ( items: EntryRecord[] ) =>
						setShowDeleteModal( items ),
				},
		  ];

	return (
		<>
			<div className="liveblog-dataview-metabox">
				{ isArchived && (
					<div className="notice notice-info inline">
						<p>
							{ __(
								'This liveblog is archived. Entries are read-only.',
								'liveblog'
							) }
						</p>
					</div>
				) }
				{ deleteError && (
					<div className="notice notice-error inline">
						<p>{ deleteError }</p>
					</div>
				) }
				{ breakoutMessage && (
					<div
						className={ `notice inline ${
							breakoutMessage.type === 'success'
								? 'notice-success'
								: 'notice-error'
						}` }
					>
						<p>{ breakoutMessage.text }</p>
					</div>
				) }
				<DataViews
					data={ filtered }
					fields={ fields }
					view={ view }
					onChangeView={ ( newView ) =>
						setView( newView as ViewState )
					}
					actions={ actions }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {} } }
					search={ true }
					searchLabel={ __( 'Search entries', 'liveblog' ) }
					getItemId={ ( item: EntryRecord ) => String( item.id ) }
					{ ...( {
						emptyElement: (
							<p>{ __( 'No entries yet.', 'liveblog' ) }</p>
						),
					} as Record< string, unknown > ) }
				/>
			</div>
			{ showDeleteModal && (
				<Modal
					title={ __( 'Delete entries', 'liveblog' ) }
					onRequestClose={ () => setShowDeleteModal( null ) }
				>
					<p>
						{ sprintf(
							/* translators: %d: number of entries to delete */
							__(
								'Delete %d entry(s)? This cannot be undone.',
								'liveblog'
							),
							showDeleteModal.length
						) }
					</p>
					<div
						style={ {
							display: 'flex',
							gap: 8,
							justifyContent: 'flex-end',
							marginTop: 16,
						} }
					>
						<Button
							variant="tertiary"
							onClick={ () => setShowDeleteModal( null ) }
						>
							{ __( 'Cancel', 'liveblog' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ () => handleDelete( showDeleteModal ) }
						>
							{ __( 'Delete', 'liveblog' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
}

let root: ReturnType< typeof createRoot > | null = null;
let nonceSetup = false;

export function mountEntriesDataView(): void {
	const container = document.getElementById( 'liveblog-entries-dataview' );
	if ( ! container ) {
		return;
	}

	let config: DataViewConfig;
	try {
		config = JSON.parse( container.dataset.config ?? '' ) as DataViewConfig;
	} catch ( e ) {
		return;
	}

	if ( ! nonceSetup ) {
		apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
		nonceSetup = true;
	}

	if ( root ) {
		root.unmount();
	}
	root = createRoot( container );
	root.render(
		<EntriesDataView
			entries={ config.entries }
			postId={ config.postId }
			isArchived={ config.isArchived }
		/>
	);
}
