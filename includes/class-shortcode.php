<?php
/**
 * Registers the [cap_player id="123"] shortcode.
 * Renders either a Mini player (circular overlay) or a Full player
 * (album-art + transport controls) depending on the CPT field value.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAP_Shortcode {

	public function __construct() {
		add_shortcode( 'cap_player', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	// ------------------------------------------------------------------
	// Asset registration
	// ------------------------------------------------------------------

	public function register_assets() {
		wp_register_style(
			'cap-player',
			CAP_PLUGIN_URL . 'assets/css/player.css',
			array(),
			CAP_VERSION
		);
		wp_register_script(
			'cap-player',
			CAP_PLUGIN_URL . 'assets/js/player.js',
			array( 'jquery' ),
			CAP_VERSION,
			true
		);
	}

	// ------------------------------------------------------------------
	// Inline SVG icons
	// ------------------------------------------------------------------

	private static function icon( $name ) {
		$icons = array(
			'play'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5,3 19,12 5,21"/></svg>',
			'pause'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
			'prev'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="19,20 9,12 19,4"/><rect x="5" y="4" width="2" height="16"/></svg>',
			'next'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5,4 15,12 5,20"/><rect x="17" y="4" width="2" height="16"/></svg>',
			'shuffle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg>',
			'loop'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>',
			'volume'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
			'mute'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>',
			'list'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
		);
		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
	}

	// ------------------------------------------------------------------
	// Volume control helper — button + popup range slider
	// ------------------------------------------------------------------

	private function volume_control() {
		ob_start();
		?>
		<div class="cap-volume-wrap">
			<div class="cap-volume-slider-wrap" aria-hidden="true">
				<input
					type="range"
					class="cap-volume-slider"
					min="0"
					max="100"
					step="5"
					value="100"
					orient="vertical"
					aria-label="<?php esc_attr_e( 'Volume', 'circular-audio-player' ); ?>"
				>
			</div>
			<button
				type="button"
				class="cap-btn cap-btn-volume"
				aria-label="<?php esc_attr_e( 'Volume', 'circular-audio-player' ); ?>"
			><?php echo self::icon( 'volume' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Shortcode entry point
	// ------------------------------------------------------------------

	public function render( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'cap_player' );
		$post_id = absint( $atts['id'] );

		if ( ! $post_id ) {
			return $this->error( __( 'Circular Audio Player: no player ID supplied.', 'circular-audio-player' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'cap_player' !== $post->post_type ) {
			return $this->error( sprintf( __( 'Circular Audio Player: no player found with ID %d.', 'circular-audio-player' ), $post_id ) );
		}

		if ( ! function_exists( 'get_field' ) ) {
			return $this->error( __( 'Circular Audio Player: Advanced Custom Fields is required.', 'circular-audio-player' ) );
		}

		// Retrieve fields.
		$player_type = get_field( 'cap_player_type', $post_id ) ?: 'mini';
		$bg_image    = get_field( 'cap_bg_image',    $post_id );
		$player_size = absint( get_field( 'cap_player_size', $post_id ) ) ?: 100;
		$bg_padding  = get_field( 'cap_bg_padding',  $post_id );
		$bg_padding  = ( $bg_padding !== '' && $bg_padding !== null ) ? absint( $bg_padding ) : 20;
		$tracks_raw  = get_field( 'cap_tracks',      $post_id );

		if ( empty( $tracks_raw ) ) {
			return $this->error( __( 'Circular Audio Player: this player has no tracks configured.', 'circular-audio-player' ) );
		}

		// Build clean track list.
		$track_list = array();
		foreach ( $tracks_raw as $t ) {
			$url = ! empty( $t['cap_audio_file']['url'] ) ? $t['cap_audio_file']['url'] : '';
			if ( ! $url ) continue;
			$fallback      = ! empty( $t['cap_audio_file']['title'] ) ? $t['cap_audio_file']['title'] : '';
			$track_list[] = array(
				'src'   => $url,
				'title' => ! empty( $t['cap_track_title'] ) ? $t['cap_track_title'] : $fallback,
			);
		}

		if ( empty( $track_list ) ) {
			return $this->error( __( 'Circular Audio Player: no valid audio files found.', 'circular-audio-player' ) );
		}

		$bg_url = ! empty( $bg_image['url'] ) ? esc_url( $bg_image['url'] ) : '';
		$bg_alt = ! empty( $bg_image['alt'] ) ? esc_attr( $bg_image['alt'] ) : '';

		wp_enqueue_style( 'cap-player' );
		wp_enqueue_script( 'cap-player' );

		if ( 'full' === $player_type ) {
			return $this->render_full( $post_id, $post, $track_list, $bg_url, $bg_alt );
		}
		return $this->render_mini( $post_id, $post, $track_list, $bg_url, $player_size, $bg_padding );
	}

	// ------------------------------------------------------------------
	// Mini player
	// ------------------------------------------------------------------

	private function render_mini( $post_id, $post, $track_list, $bg_url, $player_size, $bg_padding ) {
		$has_multiple = count( $track_list ) > 1;
		$drawer_id    = 'cap-drawer-' . absint( $post_id );
		$first        = $track_list[0];

		$stage_style = 'background-size:cover;background-position:center;';
		if ( $bg_url ) {
			$stage_style = 'background-image:url(' . $bg_url . ');' . $stage_style;
		}

		// SVG circle diameter = player width minus padding on each side.
		// Clamped to a minimum of 40px so it never disappears entirely.
		$svg_size = max( 40, $player_size - ( 2 * $bg_padding ) );

		ob_start();
		?>
		<div class="cap-player-wrapper cap-player-mini" id="cap-player-<?php echo absint( $post_id ); ?>" aria-label="<?php echo esc_attr( $post->post_title ); ?>" style="max-width:<?php echo absint( $player_size ); ?>px;">

			<div class="cap-mini-stage" style="<?php echo esc_attr( $stage_style ); ?>">

				<?php /* Circular player — SVG injected here by JS */ ?>
				<div class="mediPlayer">
					<audio
						preload="none"
						data-size="<?php echo absint( $svg_size ); ?>"
						src="<?php echo esc_url( $first['src'] ); ?>"
					></audio>
				</div>

				<?php /* Control bar overlaid at the bottom of the image */ ?>
				<div class="cap-mini-bar">
					<div class="cap-mini-bar-left">
						<button type="button" class="cap-btn cap-btn-shuffle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Shuffle', 'circular-audio-player' ); ?>"><?php echo self::icon( 'shuffle' ); ?></button>
						<button type="button" class="cap-btn cap-btn-loop"    aria-pressed="false" aria-label="<?php esc_attr_e( 'Loop',    'circular-audio-player' ); ?>"><?php echo self::icon( 'loop' ); ?></button>
						<?php echo $this->volume_control(); ?>
					</div>
					<div class="cap-mini-bar-right">
						<?php if ( $has_multiple ) : ?>
							<button
								type="button"
								class="cap-btn cap-drawer-toggle"
								aria-expanded="false"
								aria-controls="<?php echo esc_attr( $drawer_id ); ?>"
								aria-label="<?php esc_attr_e( 'Track list', 'circular-audio-player' ); ?>"
							><?php echo self::icon( 'list' ); ?></button>
						<?php endif; ?>
					</div>
				</div>

			</div><!-- .cap-mini-stage -->

			<?php if ( $has_multiple ) : ?>
				<div class="cap-track-drawer" id="<?php echo esc_attr( $drawer_id ); ?>" aria-hidden="true">
					<ol class="cap-track-list" role="list">
						<?php foreach ( $track_list as $i => $track ) : ?>
							<li
								class="cap-track-item<?php echo 0 === $i ? ' cap-track-active' : ''; ?>"
								data-src="<?php echo esc_url( $track['src'] ); ?>"
								data-title="<?php echo esc_attr( $track['title'] ); ?>"
								tabindex="0"
								role="listitem"
							>
								<div class="cap-track-name-wrap">
									<span class="cap-track-name"><?php echo esc_html( $track['title'] ); ?></span>
									<div class="cap-track-progress" aria-hidden="true">
										<div class="cap-track-progress-bar"></div>
									</div>
								</div>
								<span class="cap-track-duration">--:--</span>
								<button
									type="button"
									class="cap-btn cap-track-play-btn"
									aria-label="<?php echo esc_attr( sprintf( __( 'Play %s', 'circular-audio-player' ), $track['title'] ) ); ?>"
								><?php echo self::icon( 'play' ); ?></button>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

		</div><!-- .cap-player-wrapper -->
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Full player
	// ------------------------------------------------------------------

	private function render_full( $post_id, $post, $track_list, $bg_url, $bg_alt ) {
		$has_multiple = count( $track_list ) > 1;
		$drawer_id    = 'cap-drawer-' . absint( $post_id );
		$first        = $track_list[0];

		ob_start();
		?>
		<div class="cap-player-wrapper cap-player-full" id="cap-player-<?php echo absint( $post_id ); ?>" aria-label="<?php echo esc_attr( $post->post_title ); ?>">

			<div class="cap-full-main">

				<?php /* Album art — left column */ ?>
				<?php if ( $bg_url ) : ?>
					<div class="cap-full-art">
						<img src="<?php echo esc_url( $bg_url ); ?>" alt="<?php echo esc_attr( $bg_alt ); ?>">
					</div>
				<?php endif; ?>

				<?php /* Controls — right column */ ?>
				<div class="cap-full-player">

					<audio preload="none" src="<?php echo esc_url( $first['src'] ); ?>" style="display:none;"></audio>

					<p class="cap-now-playing"><?php echo esc_html( $first['title'] ); ?></p>

					<div class="cap-full-seek">
						<span class="cap-time-current">0:00</span>
						<div
							class="cap-full-seekbar"
							role="slider"
							tabindex="0"
							aria-label="<?php esc_attr_e( 'Seek', 'circular-audio-player' ); ?>"
							aria-valuemin="0"
							aria-valuemax="100"
							aria-valuenow="0"
						>
							<div class="cap-full-seekbar-fill"></div>
						</div>
						<span class="cap-time-total">--:--</span>
					</div>

					<?php /* Single controls row: transport LEFT — secondary RIGHT */ ?>
					<div class="cap-full-controls-row">

						<div class="cap-full-transport">
							<button type="button" class="cap-btn cap-btn-prev" aria-label="<?php esc_attr_e( 'Previous track', 'circular-audio-player' ); ?>"><?php echo self::icon( 'prev' ); ?></button>
							<button type="button" class="cap-btn cap-btn-play" aria-label="<?php esc_attr_e( 'Play', 'circular-audio-player' ); ?>"><?php echo self::icon( 'play' ); ?></button>
							<button type="button" class="cap-btn cap-btn-next" aria-label="<?php esc_attr_e( 'Next track', 'circular-audio-player' ); ?>"><?php echo self::icon( 'next' ); ?></button>
						</div>

						<div class="cap-full-secondary">
							<button type="button" class="cap-btn cap-btn-shuffle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Shuffle', 'circular-audio-player' ); ?>"><?php echo self::icon( 'shuffle' ); ?></button>
							<button type="button" class="cap-btn cap-btn-loop"    aria-pressed="false" aria-label="<?php esc_attr_e( 'Loop',    'circular-audio-player' ); ?>"><?php echo self::icon( 'loop' ); ?></button>
							<?php echo $this->volume_control(); ?>
							<?php if ( $has_multiple ) : ?>
								<button
									type="button"
									class="cap-btn cap-drawer-toggle"
									aria-expanded="false"
									aria-controls="<?php echo esc_attr( $drawer_id ); ?>"
									aria-label="<?php esc_attr_e( 'Track list', 'circular-audio-player' ); ?>"
								><?php echo self::icon( 'list' ); ?></button>
							<?php endif; ?>
						</div>

					</div><!-- .cap-full-controls-row -->

				</div><!-- .cap-full-player -->

			</div><!-- .cap-full-main -->

			<?php if ( $has_multiple ) : ?>
				<div class="cap-track-drawer" id="<?php echo esc_attr( $drawer_id ); ?>" aria-hidden="true">
					<ol class="cap-track-list" role="list">
						<?php foreach ( $track_list as $i => $track ) : ?>
							<li
								class="cap-track-item<?php echo 0 === $i ? ' cap-track-active' : ''; ?>"
								data-src="<?php echo esc_url( $track['src'] ); ?>"
								data-title="<?php echo esc_attr( $track['title'] ); ?>"
								tabindex="0"
								role="button"
								aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>"
							>
								<span class="cap-track-name"><?php echo esc_html( $track['title'] ); ?></span>
								<span class="cap-track-duration">--:--</span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

		</div><!-- .cap-player-wrapper -->
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Error helper
	// ------------------------------------------------------------------

	private function error( $message ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return '<p class="cap-player-error">' . esc_html( $message ) . '</p>';
		}
		return '';
	}
}
