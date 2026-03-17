<?php
/**
 * Registers ACF field groups for the Audio Player CPT.
 * Fields are defined in PHP so no JSON/DB export is needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAP_ACF_Fields {

	public function __construct() {
		add_action( 'acf/init', array( $this, 'register_fields' ) );
	}

	public function register_fields() {

		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( array(
			'key'                   => 'group_cap_player',
			'title'                 => __( 'Audio Player Settings', 'circular-audio-player' ),
			'fields'                => array(

				// --- Player Style -----------------------------------------------
				array(
					'key'           => 'field_cap_player_type',
					'label'         => __( 'Player Style', 'circular-audio-player' ),
					'name'          => 'cap_player_type',
					'type'          => 'button_group',
					'instructions'  => __( 'Mini: circular overlay on image. Full: album art with transport bar.', 'circular-audio-player' ),
					'required'      => 0,
					'choices'       => array(
						'mini' => __( 'Mini', 'circular-audio-player' ),
						'full' => __( 'Full', 'circular-audio-player' ),
					),
					'default_value' => 'mini',
					'return_format' => 'value',
				),

				// --- Background Image -------------------------------------------
				array(
					'key'           => 'field_cap_bg_image',
					'label'         => __( 'Background Image', 'circular-audio-player' ),
					'name'          => 'cap_bg_image',
					'type'          => 'image',
					'instructions'  => __( 'Image displayed behind the circular player controls.', 'circular-audio-player' ),
					'required'      => 0,
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),

				// --- Player Size (mini only) ------------------------------------
				array(
					'key'               => 'field_cap_player_size',
					'label'             => __( 'Player Size (px)', 'circular-audio-player' ),
					'name'              => 'cap_player_size',
					'type'              => 'number',
					'instructions'      => __( 'Sets the max-width of the mini player and the diameter of the circular control. Default: 300.', 'circular-audio-player' ),
					'required'          => 0,
					'default_value'     => 300,
					'min'               => 100,
					'max'               => 800,
					'step'              => 1,
					'prepend'           => '',
					'append'            => 'px',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_cap_player_type',
								'operator' => '==',
								'value'    => 'mini',
							),
						),
					),
				),

				// --- Background Padding (mini only) ----------------------------
				array(
					'key'               => 'field_cap_bg_padding',
					'label'             => __( 'Background Padding (px)', 'circular-audio-player' ),
					'name'              => 'cap_bg_padding',
					'type'              => 'number',
					'instructions'      => __( 'Space between the edge of the image and the circular control. Default: 20.', 'circular-audio-player' ),
					'required'          => 0,
					'default_value'     => 20,
					'min'               => 0,
					'max'               => 200,
					'step'              => 1,
					'prepend'           => '',
					'append'            => 'px',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_cap_player_type',
								'operator' => '==',
								'value'    => 'mini',
							),
						),
					),
				),

				// --- Tracks (Repeater) -----------------------------------------
				array(
					'key'           => 'field_cap_tracks',
					'label'         => __( 'Tracks', 'circular-audio-player' ),
					'name'          => 'cap_tracks',
					'type'          => 'repeater',
					'instructions'  => __( 'Add one or more audio tracks to this player.', 'circular-audio-player' ),
					'required'      => 1,
					'min'           => 1,
					'max'           => 0,
					'layout'        => 'block',
					'button_label'  => __( 'Add Track', 'circular-audio-player' ),
					'sub_fields'    => array(

						array(
							'key'           => 'field_cap_track_title',
							'label'         => __( 'Track Title', 'circular-audio-player' ),
							'name'          => 'cap_track_title',
							'type'          => 'text',
							'instructions'  => __( 'Displayed beneath the player circle.', 'circular-audio-player' ),
							'required'      => 0,
							'placeholder'   => __( 'e.g. Piano Sonata No. 1', 'circular-audio-player' ),
							'wrapper'       => array( 'width' => '50' ),
						),

						array(
							'key'            => 'field_cap_audio_file',
							'label'          => __( 'Audio File', 'circular-audio-player' ),
							'name'           => 'cap_audio_file',
							'type'           => 'file',
							'instructions'   => __( 'Upload or select an MP3, OGG, or WAV file.', 'circular-audio-player' ),
							'required'       => 1,
							'return_format'  => 'array',
							'library'        => 'all',
							'mime_types'     => 'mp3, ogg, wav, m4a',
							'wrapper'        => array( 'width' => '50' ),
						),

					),
				),

			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'cap_player',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => array( 'the_content', 'excerpt', 'discussion', 'comments', 'revisions', 'slug', 'author', 'format', 'page_attributes', 'featured_image', 'categories', 'tags', 'send-trackbacks' ),
			'active'                => true,
		) );
	}
}
