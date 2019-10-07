<?php

class Liveblog_Slack_Process_Entry_Async_Task extends WP_Async_Task {

	protected $action = 'slack_process_entry';

	/**
	 * Prepare data for the asynchronous request
	 *
	 * @param array $data An array of data sent to the hook
	 *
	 * @return array
	 * @throws Exception If for any reason the request should not happen
	 *
	 */
	protected function prepare_data( $data ) {
		return [ 'entry_data' => $data[0] ];
	}

	/**
	 * Run the async task action
	 */
	protected function run_action() {
		$entry_data = $_POST['entry_data'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
		if ( ! empty( $entry_data ) ) {
			do_action( 'wp_async_slack_process_entry', $entry_data );
		}

	}

}
