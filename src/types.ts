/** Configuration injected into the frontend via wp_localize_script. */
export interface LiveblogPollingConfig {
	polling_interval: number;
}

/** Settings injected into the admin via wp_localize_script. */
export interface LiveblogAdminSettings {
	endpoint_url: string;
	nonce_key: string;
	nonce: string;
	error_message_template: string;
	short_error_message_template: string;
}

/** A single entry record passed to the DataViews component. */
export interface EntryRecord {
	id: number;
	title: string;
	content: string;
	author: string;
	date: string;
	timestamp: number;
	permalink: string;
	breakout_post_id: number | null;
	breakout_status: string | null;
	thumbnail: string | null;
	[ key: string ]: unknown;
}

/** DataViews sort configuration. */
export interface ViewSort {
	field: string;
	direction: 'asc' | 'desc';
}

/** DataViews layout configuration. */
export interface ViewLayout {
	density?: 'comfortable' | 'compact';
	styles?: Record<
		string,
		{ width?: string | number; minWidth?: string | number }
	>;
}

/** DataViews view state. */
export interface ViewState {
	type: 'table';
	perPage: number;
	page: number;
	search: string;
	sort: ViewSort;
	titleField: string;
	fields: string[];
	layout: ViewLayout;
}

/** Pagination info from filterSortAndPaginate. */
export interface PaginationInfo {
	totalItems: number;
	totalPages: number;
}

/** Result from filterSortAndPaginate. */
export interface FilteredData {
	data: EntryRecord[];
	paginationInfo: PaginationInfo;
}

/** EntriesDataView component props. */
export interface EntriesDataViewProps {
	entries: EntryRecord[];
	postId: number;
	isArchived: boolean;
}

/** Config injected into the DataViews mount point via data-config attribute. */
export interface DataViewConfig {
	entries: EntryRecord[];
	postId: number;
	nonce: string;
	isArchived: boolean;
}

/** Breakout API response. */
export interface BreakoutResponse {
	breakout_post_id: number;
}

/** Breakout message state. */
export interface BreakoutMessage {
	type: 'success' | 'error';
	text: string;
}

/** CRUD API response for toggle state. */
export interface ToggleStateResponse {
	metabox_html: string;
}

export {};

declare global {
	interface Window {
		liveblogPollingConfig?: LiveblogPollingConfig;
		liveblogPoller?: import('./js/poll').default;
		liveblog_admin_settings: LiveblogAdminSettings;
	}
}
