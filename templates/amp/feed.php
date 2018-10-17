<?php
$post_id          = $this->get( 'post_id' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
$links            = $this->get( 'links' );
$page             = $this->get( 'page' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
$pages            = $this->get( 'pages' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
$last             = $this->get( 'last' );
$settings         = $this->get( 'settings' );
$entries_per_page = $settings['entries_per_page'];
$refresh_interval = $settings['refresh_interval'];
$social           = $settings['social'];
?>

<div class="amp-wp-article-liveblog">

<amp-live-list
	layout="container"
	data-poll-interval="<?php echo esc_attr( $refresh_interval ); ?>"
	data-max-items-per-page="<?php echo esc_attr( $entries_per_page ); ?>"
	id="amp-live-list-insert-blog">

	<button id="live-list-update-button"
		update
		<?php if ( false === $last ) : ?>
			on="tap:amp-live-list-insert-blog.update"
		<?php else : ?>
			on="tap:AMP.navigateTo(url='<?php echo esc_url( $links->base ); ?>')"
		<?php endif ?>
		class="ampstart-btn caps"><?php esc_html_e( 'You have updates' ); ?></button>
	<div items>

	<?php foreach ( $this->get( 'entries' ) as $entry ) : ?>
		<?php
		$this->load_part(
			'entry',
			array(
				'post_id'        => $post_id,
				'id'             => $entry->id,
				'content'        => $entry->content,
				'authors'        => $entry->authors,
				'time'           => $entry->entry_time,
				'date'           => $entry->date,
				'time_ago'       => $entry->time_ago,
				'share_link'     => $entry->share_link,
				'social'         => $social,
				'update_time'    => $entry->update_time,
				'share_link_amp' => $entry->share_link_amp,
			)
		);
		?>
	<?php endforeach; ?>
	</div>


	<?php
	$this->load_part(
		'pagination',
		array(
			'links' => $links,
			'page'  => $page,
			'pages' => $pages,
		)
	);
	?>

</amp-live-list>
</div>
