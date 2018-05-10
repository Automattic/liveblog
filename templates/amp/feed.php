<?php

$links  = $this->get( 'links' );
$page   = $this->get( 'page' );
$pages  = $this->get( 'pages' );

?>

<div class="amp-wp-article-liveblog">


<amp-live-list
	layout="container"
	data-poll-interval="15000"
	data-max-items-per-page="1"
	id="amp-live-list-insert-blog">

	<button id="live-list-update-button"
		update
		on="tap:amp-live-list-insert-blog.update"
		class="ampstart-btn caps"><?php esc_html_e( 'You have updates' ); ?></button>
	<div items>

	<?php foreach ( $this->get( 'entries' ) as $entry ): ?>

		<?php $this->load_part( 'entry', array(
			'content' => $entry->amp_content,
			'authors' => $entry->authors,
			'time'	  => $entry->entry_time
		) ); ?>
	<?php endforeach; ?>
	</div>


	<?php $this->load_part( 'pagination', array(
		'links' => $links,
		'page' => $page,
		'pages' => $pages
	) ); ?>

</amp-live-list>
</div>
