<?php
	$entry_time = $this->get( 'time' );
	$content = $this->get( 'content' );
?>

<div class="liveblog-entry" id="post<?php echo esc_attr( $entry_time ); ?>"
	data-sort-time="<?php echo esc_attr( $entry_time ); ?>">

	<aside class="liveblog-entry-aside">
		<a class="liveblog-meta-time" href="#" target="_blank">
			<span><?php echo esc_html( $this->get( 'time_ago' ) ); ?></span>
			<span><?php echo esc_html( $this->get( 'date' ) ); ?> </span>
		</a>
	</aside>

	<div class="liveblog-entry-main">
		<header class="liveblog-meta-authors">

			<?php if (is_array( $this->get( 'authors' ))): ?>

				<?php foreach ( $this->get( 'authors' ) as $author ) : ?>

					<?php
					$this->load_part(
						'author', array(
							'author' => $author,
						)
					);
					?>

				<?php endforeach ?>


				<?php else : ?>

				<?php
				$this->load_part(
					'author', array(
						'author' => $authors,
					)
				);
				?>

			<?php endif; ?>
		</header>

		<div class="liveblog-entry-content">
			<?php echo $this->get( 'content' ); ?>

			<amp-social-share type="twitter"
				width="45"
				height="33"
				data-param-url="<?php echo esc_html( $this->get( 'share_link' ) ); ?>"></amp-social-share>
			<amp-social-share type="facebook"
				width="45"
				height="33"
				data-attribution="254325784911610"
				data-param-url="<?php echo esc_html( $this->get( 'share_link' ) ); ?>"></amp-social-share>
			<amp-social-share type="gplus"
				width="45"
				height="33"
				data-param-url="<?php echo esc_html( $this->get( 'share_link' ) ); ?>"></amp-social-share>
			<amp-social-share type="email"
				width="45"
				height="33"
				data-param-url="<?php echo esc_html( $this->get( 'share_link' ) ); ?>"></amp-social-share>
			<amp-social-share type="pinterest"
				width="45"
				height="33"
				data-param-url="<?php echo esc_html( $this->get( 'share_link' ) ); ?>"></amp-social-share>
		</div>
	</div>
</div>
