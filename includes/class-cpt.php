<?php
/**
 * Registers the Audio Player custom post type.
 * The CPT is admin-only — no public permalink or frontend archive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAP_CPT {

	public function __construct() {
		add_action( 'init',                  array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register() {

		$labels = array(
			'name'               => __( 'Audio Players',           'circular-audio-player' ),
			'singular_name'      => __( 'Audio Player',            'circular-audio-player' ),
			'add_new'            => __( 'Add New Player',           'circular-audio-player' ),
			'add_new_item'       => __( 'Add New Audio Player',     'circular-audio-player' ),
			'edit_item'          => __( 'Edit Audio Player',        'circular-audio-player' ),
			'new_item'           => __( 'New Audio Player',         'circular-audio-player' ),
			'view_item'          => __( 'View Audio Player',        'circular-audio-player' ),
			'search_items'       => __( 'Search Audio Players',     'circular-audio-player' ),
			'not_found'          => __( 'No audio players found',   'circular-audio-player' ),
			'not_found_in_trash' => __( 'No audio players in trash', 'circular-audio-player' ),
			'menu_name'          => __( 'Audio Players',            'circular-audio-player' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Circular HTML5 audio players managed via shortcode.', 'circular-audio-player' ),
			'public'              => false,   // No frontend access whatsoever.
			'publicly_queryable'  => false,   // Block direct URL queries.
			'show_ui'             => true,    // Show in WP admin.
			'show_in_menu'        => true,    // Appear in admin sidebar.
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'query_var'           => false,
			'rewrite'             => false,   // No permalink slug needed.
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-controls-volumeon',
			'supports'            => array( 'title' ),
			'capabilities'        => array(
				'create_posts' => 'manage_options',
			),
			'map_meta_cap'        => true,
		);

		register_post_type( 'cap_player', $args );

		// Remove the default "View" / "Preview" row actions so editors
		// are never shown a dead frontend URL.
		add_filter( 'post_row_actions', array( $this, 'remove_row_actions' ), 10, 2 );

		// Display the shortcode on the list-table screen for easy copying.
		add_filter( 'manage_cap_player_posts_columns',       array( $this, 'add_shortcode_column' ) );
		add_action( 'manage_cap_player_posts_custom_column', array( $this, 'render_shortcode_column' ), 10, 2 );
	}

	/**
	 * Enqueue a small inline script on the cap_player list table screen
	 * that adds click-to-copy behaviour to the shortcode column.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {

		// Only load on the post-list screen for this CPT.
		if ( 'edit.php' !== $hook ) {
			return;
		}
		if ( ! isset( $_GET['post_type'] ) || 'cap_player' !== $_GET['post_type'] ) {
			return;
		}

		// Tiny inline style — no separate file needed.
		wp_add_inline_style( 'list-tables', '
			.cap-copy-btn {
				display:         inline-flex;
				align-items:     center;
				gap:             6px;
				background:      none;
				border:          1px solid #c3c4c7;
				border-radius:   3px;
				padding:         2px 7px 2px 5px;
				cursor:          pointer;
				font-size:       12px;
				color:           #50575e;
				vertical-align:  middle;
				margin-left:     6px;
				transition:      background 0.15s, color 0.15s;
			}
			.cap-copy-btn:hover {
				background: #f0f0f1;
				color:      #1d2327;
			}
			.cap-copy-btn.cap-copied {
				background:   #00a32a;
				border-color: #00a32a;
				color:        #fff;
			}
			.cap-shortcode-wrap {
				display:     flex;
				align-items: center;
				gap:         4px;
			}
		' );

		// Inline script — uses the Clipboard API with a textarea fallback.
		wp_add_inline_script( 'common', '
			( function () {
				function copyShortcode( btn ) {
					var text = btn.dataset.shortcode;

					function onSuccess() {
						btn.classList.add( "cap-copied" );
						btn.textContent = "Copied!";
						setTimeout( function () {
							btn.classList.remove( "cap-copied" );
							btn.innerHTML = "&#10697; Copy";
						}, 2000 );
					}

					if ( navigator.clipboard && window.isSecureContext ) {
						navigator.clipboard.writeText( text ).then( onSuccess );
					} else {
						// Fallback for older browsers / non-HTTPS.
						var ta = document.createElement( "textarea" );
						ta.value = text;
						ta.style.cssText = "position:fixed;top:-9999px;left:-9999px;";
						document.body.appendChild( ta );
						ta.focus();
						ta.select();
						try { document.execCommand( "copy" ); onSuccess(); } catch(e) {}
						document.body.removeChild( ta );
					}
				}

				document.addEventListener( "click", function ( e ) {
					var btn = e.target.closest( ".cap-copy-btn" );
					if ( btn ) {
						e.preventDefault();
						copyShortcode( btn );
					}
				} );
			} )();
		' );
	}

	/**
	 * Strip View / Preview links from the row actions.
	 */
	public function remove_row_actions( $actions, $post ) {
		if ( 'cap_player' === $post->post_type ) {
			unset( $actions['view'] );
			unset( $actions['preview'] );
		}
		return $actions;
	}

	/**
	 * Add a "Shortcode" column to the posts list table.
	 */
	public function add_shortcode_column( $columns ) {
		$columns['cap_shortcode'] = __( 'Shortcode', 'circular-audio-player' );
		return $columns;
	}

	/**
	 * Render the shortcode string + copy button in the list-table column.
	 */
	public function render_shortcode_column( $column, $post_id ) {
		if ( 'cap_shortcode' === $column ) {
			$shortcode = sprintf( '[cap_player id="%d"]', absint( $post_id ) );
			printf(
				'<span class="cap-shortcode-wrap">' .
					'<code>%s</code>' .
					'<button type="button" class="cap-copy-btn" data-shortcode="%s" title="%s">&#10697; Copy</button>' .
				'</span>',
				esc_html( $shortcode ),
				esc_attr( $shortcode ),
				esc_attr__( 'Copy shortcode to clipboard', 'circular-audio-player' )
			);
		}
	}
}
