<?php
	$id             = $this->get( 'id' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$entry_time     = $this->get( 'time' );
	$content        = apply_filters( 'the_content', $this->get( 'content' ) );
	$social         = $this->get( 'social' );
	$single         = $this->get( 'single' );
	$share_link     = $this->get( 'share_link' );
	$update_time    = $this->get( 'update_time' );
	$share_link_amp = $this->get( 'share_link_amp' );

	/* This filter is defined in class-wpcom-liveblog-amp.php */
	$facebook_app_id = apply_filters( 'liveblog_amp_facebook_share_app_id', false );
?>

<div class="liveblog-entry" id="post<?php echo esc_attr( $update_time ); ?>"
	data-sort-time="<?php echo esc_attr( $entry_time ); ?>">

	<aside class="liveblog-entry-aside">
		<a class="liveblog-meta-time" href="#" target="_blank">
			<span><?php echo esc_html( $this->get( 'time_ago' ) ); ?></span>
			<span><?php echo esc_html( $this->get( 'date' ) ); ?> </span>
		</a>
	</aside>

	<div class="liveblog-entry-main">
		<header class="liveblog-meta-authors">

			<?php if ( is_array( $this->get( 'authors' ) ) ) : ?>

				<?php foreach ( $this->get( 'authors' ) as $author ) : ?>

					<?php
					$this->load_part(
						'author',
						[
							'author' => $author,
						]
					);
					?>

				<?php endforeach ?>


				<?php else : ?>

					<?php
					$this->load_part(
						'author',
						[
							'author' => $authors,
						]
					);
					?>

			<?php endif; ?>
		</header>

		<div class="liveblog-entry-content">
			<?php echo $this->get( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php if ( $single ) : ?>
				<a href="<?php echo esc_url( $share_link ); ?>">View in feed</a>
			<?php endif; ?>

			<?php if ( $social && count( $social ) > 1 ) : ?>
				<?php foreach ( $social as $platform ) : ?>
				<amp-social-share type="<?php echo esc_attr( $platform ); ?>"
					width="45"
					height="33"
					data-param-url="<?php echo esc_url( $share_link_amp ); ?>"
					<?php if ( 'facebook' === $platform ) : ?>
						data-param-app_id="<?php echo esc_attr( $facebook_app_id ); ?>"
					<?php endif; ?>
				></amp-social-share>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
