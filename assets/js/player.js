/**
 * Circular Audio Player — WordPress plugin JS
 * Supports: Mini (circular SVG overlay) and Full (transport controls) player types.
 */
( function ( $ ) {

	'use strict';

	// SVG circumference for the circular progress arc (2π × 47.45).
	var PC = 298.1371428256714;

	// -----------------------------------------------------------------------
	// Icon HTML — must match PHP icon() output for dynamic swapping.
	// -----------------------------------------------------------------------
	var ICON = {
		play:  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5,3 19,12 5,21"/></svg>',
		pause: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
		vol:   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
		mute:  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>'
	};

	// -----------------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------------

	function formatTime( s ) {
		if ( isNaN( s ) || ! isFinite( s ) ) { return '--:--'; }
		var m = Math.floor( s / 60 );
		var sec = Math.floor( s % 60 );
		return m + ':' + ( sec < 10 ? '0' : '' ) + sec;
	}

	/** Update a circular SVG arc's stroke-dashoffset. */
	function setArcValue( $arc, current, duration ) {
		if ( ! duration || isNaN( duration ) ) { return; }
		$arc.css( 'strokeDashoffset', PC - ( ( current / duration ) * PC ) );
	}

	/**
	 * Preload just metadata for each track item to populate duration labels.
	 * Stores the total seconds on the item as data('cap-total-dur') so the
	 * mini player can restore the label after a countdown.
	 */
	function preloadDurations( $items ) {
		$items.each( function () {
			var $item = $( this );
			var src   = $item.data( 'src' );
			if ( ! src ) { return; }
			var tmp     = new Audio();
			tmp.preload = 'metadata';
			tmp.addEventListener( 'loadedmetadata', function () {
				$item.data( 'cap-total-dur', tmp.duration );
				$item.find( '.cap-track-duration' ).text( formatTime( tmp.duration ) );
				tmp.src = '';
			} );
			tmp.src = src;
		} );
	}

	/**
	 * Pause every other wrapper by calling the 'cap-external-pause' handler
	 * each init function stores on its wrapper element.
	 */
	function pauseAllExcept( $current ) {
		$( '.cap-player-wrapper' ).each( function () {
			if ( this === $current[ 0 ] ) { return; }
			var fn = $( this ).data( 'cap-external-pause' );
			if ( typeof fn === 'function' ) { fn(); }
		} );
	}

	/** Build the circular SVG control and return the HTML string. */
	function buildSVG( svgId, size ) {
		return [
			'<svg viewBox="0 0 100 100" id="' + svgId + '" version="1.1"',
			'     xmlns="http://www.w3.org/2000/svg"',
			'     width="' + size + '" height="' + size + '"',
			'     data-play="' + svgId + '" class="not-started playable">',
			'  <g class="shape">',
			'    <circle class="progress-track" cx="50" cy="50" r="47.45" stroke="#ffffff" stroke-opacity="0.25" stroke-linecap="square" fill="none" stroke-width="10"/>',
			'    <circle class="precache-bar"   cx="50" cy="50" r="47.45" stroke="#ffffff" stroke-opacity="0.25" stroke-linecap="square" fill="none" stroke-width="10" transform="rotate(-90 50 50)"/>',
			'    <circle class="progress-bar"   cx="50" cy="50" r="47.45" stroke="#ffffff" stroke-opacity="1"    stroke-linecap="square" fill="none" stroke-width="10" transform="rotate(-90 50 50)"/>',
			'  </g>',
			'  <circle class="controls" cx="50" cy="50" r="45" stroke="none" fill="#ffffff" opacity="0.0" pointer-events="all"/>',
			'  <g class="control pause">',
			'    <line x1="40" y1="35" x2="40" y2="65" stroke="#ffffff" fill="none" stroke-width="8" stroke-linecap="square"/>',
			'    <line x1="60" y1="35" x2="60" y2="65" stroke="#ffffff" fill="none" stroke-width="8" stroke-linecap="square"/>',
			'  </g>',
			'  <g class="control play">',
			'    <polygon points="45,35 65,50 45,65" fill="#ffffff" stroke-width="0"></polygon>',
			'  </g>',
			'  <g class="control stop">',
			'    <rect x="35" y="35" width="30" height="30" stroke="#ffffff" fill="none" stroke-width="1"/>',
			'  </g>',
			'</svg>'
		].join( '\n' );
	}

	// -----------------------------------------------------------------------
	// Shared toggle-button helpers (shuffle / loop / volume)
	// -----------------------------------------------------------------------

	function initShuffleLoop( $wrapper, getIsShuffled, setIsShuffled, getIsLooped, setIsLooped ) {
		$wrapper.find( '.cap-btn-shuffle' ).on( 'click', function () {
			var val = ! getIsShuffled();
			setIsShuffled( val );
			$( this ).attr( 'aria-pressed', String( val ) ).toggleClass( 'cap-btn-active', val );
		} );
		$wrapper.find( '.cap-btn-loop' ).on( 'click', function () {
			var val = ! getIsLooped();
			setIsLooped( val );
			$( this ).attr( 'aria-pressed', String( val ) ).toggleClass( 'cap-btn-active', val );
		} );
	}

	function initVolume( $wrapper, audio ) {
		var $wrap   = $wrapper.find( '.cap-volume-wrap' );
		var $btn    = $wrap.find( '.cap-btn-volume' );
		var $slider = $wrap.find( '.cap-volume-slider' );

		// Update the icon to reflect current volume (0 = mute icon, >0 = vol icon).
		function syncIcon() {
			var silent = audio.muted || audio.volume === 0;
			$btn.html( silent ? ICON.mute : ICON.vol )
				.attr( 'aria-label', 'Volume' );
		}

		// Clicking the button opens / closes the popup slider.
		$btn.on( 'click', function ( e ) {
			e.stopPropagation();
			$wrap.toggleClass( 'cap-volume-open' );
		} );

		// Keep slider interaction inside the popup — don't let it bubble and close.
		$wrap.on( 'click', function ( e ) {
			e.stopPropagation();
		} );

		// Moving the slider adjusts volume.
		$slider.on( 'input', function () {
			var pct      = parseInt( this.value, 10 ) / 100;
			audio.volume = pct;
			audio.muted  = ( pct === 0 );
			syncIcon();
		} );

		// Close the popup when clicking anywhere outside the wrap.
		$( document ).on( 'click.cap-vol-' + $wrapper.attr( 'id' ), function () {
			$wrap.removeClass( 'cap-volume-open' );
		} );
	}

	function initDrawerToggle( $wrapper ) {
		var $toggle = $wrapper.find( '.cap-drawer-toggle' );
		var $drawer = $wrapper.find( '.cap-track-drawer' );
		$toggle.on( 'click', function () {
			var open = $toggle.attr( 'aria-expanded' ) === 'true';
			$toggle.attr( 'aria-expanded', String( ! open ) );
			$drawer.toggleClass( 'cap-drawer-open', ! open ).attr( 'aria-hidden', String( open ) );
		} );
	}

	/** Return the next track index given current state. Returns -1 at end of list. */
	function nextIndex( current, count, isShuffled, isLooped ) {
		if ( isShuffled ) {
			var pool = [];
			for ( var i = 0; i < count; i++ ) { if ( i !== current ) { pool.push( i ); } }
			if ( ! pool.length ) { return isLooped ? current : -1; }
			return pool[ Math.floor( Math.random() * pool.length ) ];
		}
		if ( current < count - 1 ) { return current + 1; }
		return isLooped ? 0 : -1;
	}

	/** Return the previous track index. */
	function prevIndex( current, count, isShuffled, isLooped ) {
		if ( isShuffled ) { return nextIndex( current, count, true, isLooped ); }
		if ( current > 0 ) { return current - 1; }
		return isLooped ? count - 1 : 0;
	}

	// -----------------------------------------------------------------------
	// MINI PLAYER
	// -----------------------------------------------------------------------

	function initMini( $wrapper, idx ) {

		var $mediPlayer  = $wrapper.find( '.mediPlayer' );
		var $audio       = $wrapper.find( 'audio' );
		var audio        = $audio[ 0 ];
		var $trackItems  = $wrapper.find( '.cap-track-item' );
		var trackCount   = $trackItems.length;

		if ( ! $mediPlayer.length || ! audio ) { return; }

		// Inject SVG.
		var size  = parseInt( $audio.attr( 'data-size' ), 10 ) || 100;
		var svgId = 'cap-svg-' + idx;
		$mediPlayer.append( buildSVG( svgId, size ) );

		var $playObj  = $mediPlayer.find( '[data-play]' );
		var $progress = $mediPlayer.find( '.progress-bar' );
		var $precache = $mediPlayer.find( '.precache-bar' );

		// Build track data from DOM.
		var tracks = [];
		$trackItems.each( function () {
			tracks.push( { src: $( this ).data( 'src' ), title: $( this ).data( 'title' ), $item: $( this ) } );
		} );

		var currentIndex     = 0;
		var timeUpdateHandler = null;

		// Mutable state via closures for shared helpers.
		var isShuffled = false;
		var isLooped   = false;

		// -------------------------------------------------------------------
		// Internal helpers
		// -------------------------------------------------------------------

		function getState() {
			return ( $playObj.attr( 'class' ) || '' ).replace( /\bplayable\b/g, '' ).trim() || 'not-started';
		}

		function bindTimeUpdate() {
			timeUpdateHandler = function () {
				setArcValue( $progress, audio.currentTime, audio.duration );
				var pct = audio.duration ? ( audio.currentTime / audio.duration ) * 100 : 0;
				var $activeItem = tracks[ currentIndex ].$item;
				$activeItem.find( '.cap-track-progress-bar' ).css( 'width', pct + '%' );
				// Countdown: show remaining time next to the progress bar.
				var remaining = audio.duration ? audio.duration - audio.currentTime : 0;
				$activeItem.find( '.cap-track-duration' ).text( formatTime( remaining ) );
			};
			audio.addEventListener( 'timeupdate', timeUpdateHandler );
		}

		/** Restore a track item's duration label back to its total time. */
		function restoreDuration( $item ) {
			var total = $item.data( 'cap-total-dur' );
			$item.find( '.cap-track-duration' ).text( total ? formatTime( total ) : '--:--' );
		}

		function detachTimeUpdate() {
			if ( timeUpdateHandler ) {
				audio.removeEventListener( 'timeupdate', timeUpdateHandler );
				timeUpdateHandler = null;
			}
		}

		function resetSVG() {
			$playObj.attr( 'class', 'not-started playable' );
			$progress.css( 'strokeDashoffset', PC );
			$wrapper.removeClass( 'cap-is-playing' );
		}

		function resetPlayer() {
			detachTimeUpdate();
			resetSVG();
			// Restore the active track's duration label from countdown back to total.
			if ( tracks[ currentIndex ] ) { restoreDuration( tracks[ currentIndex ].$item ); }
		}

		function setTrackBtn( $item, isPlaying ) {
			$item.find( '.cap-track-play-btn' ).html( isPlaying ? ICON.pause : ICON.play )
				.attr( 'aria-label', ( isPlaying ? 'Pause ' : 'Play ' ) + ( $item.data( 'title' ) || '' ) );
		}

		function switchTrack( newIdx, autoPlay ) {
			var wasPlaying = getState() === 'playing';
			audio.pause();
			resetPlayer();

			// Reset old item.
			if ( tracks[ currentIndex ] ) {
				var $old = tracks[ currentIndex ].$item;
				$old.removeClass( 'cap-track-active' );
				$old.find( '.cap-track-progress-bar' ).css( 'width', '0%' );
				setTrackBtn( $old, false );
				restoreDuration( $old );
			}

			currentIndex = newIdx;
			audio.src    = tracks[ currentIndex ].src;
			tracks[ currentIndex ].$item.addClass( 'cap-track-active' );

			if ( autoPlay || wasPlaying ) {
				audio.play();
				bindTimeUpdate();
				audio.addEventListener( 'ended', onEnded );
				$playObj.attr( 'class', 'playing' );
				$wrapper.addClass( 'cap-is-playing' );
				setTrackBtn( tracks[ currentIndex ].$item, true );
			}
		}

		function onEnded() {
			audio.removeEventListener( 'ended', onEnded );
			var n = nextIndex( currentIndex, trackCount, isShuffled, isLooped );
			if ( n !== -1 ) { switchTrack( n, true ); } else { resetPlayer(); setTrackBtn( tracks[ currentIndex ].$item, false ); }
		}

		// -------------------------------------------------------------------
		// Circular SVG click
		// -------------------------------------------------------------------
		$mediPlayer.find( '.controls' ).on( 'click', function () {
			var state = getState();
			if ( state === 'not-started' || state === 'ended' ) {
				pauseAllExcept( $wrapper );
				audio.play();
				bindTimeUpdate();
				audio.addEventListener( 'ended', onEnded );
				$playObj.attr( 'class', 'playing' );
				$wrapper.addClass( 'cap-is-playing' );
				setTrackBtn( tracks[ currentIndex ].$item, true );
			} else if ( state === 'playing' ) {
				audio.pause();
				detachTimeUpdate();
				$playObj.attr( 'class', 'playable paused' );
				$wrapper.removeClass( 'cap-is-playing' );
				setTrackBtn( tracks[ currentIndex ].$item, false );
			} else if ( state === 'paused' ) {
				pauseAllExcept( $wrapper );
				audio.play();
				bindTimeUpdate();
				$playObj.attr( 'class', 'playable playing' );
				$wrapper.addClass( 'cap-is-playing' );
				setTrackBtn( tracks[ currentIndex ].$item, true );
			}
		} );

		// -------------------------------------------------------------------
		// Per-track play/pause button
		// -------------------------------------------------------------------
		$wrapper.on( 'click', '.cap-track-play-btn', function ( e ) {
			e.stopPropagation();
			var itemIdx = $trackItems.index( $( this ).closest( '.cap-track-item' ) );
			if ( itemIdx === currentIndex ) {
				$mediPlayer.find( '.controls' ).trigger( 'click' ); // Delegate to SVG control.
			} else {
				pauseAllExcept( $wrapper );
				switchTrack( itemIdx, true );
			}
		} );

		// -------------------------------------------------------------------
		// Track item row click (anywhere except the button)
		// -------------------------------------------------------------------
		$wrapper.on( 'click keydown', '.cap-track-item', function ( e ) {
			if ( $( e.target ).closest( '.cap-track-play-btn' ).length ) { return; }
			if ( e.type === 'keydown' && e.which !== 13 && e.which !== 32 ) { return; }
			if ( e.type === 'keydown' ) { e.preventDefault(); }
			var itemIdx = $trackItems.index( $( this ) );
			if ( itemIdx === currentIndex ) {
				$mediPlayer.find( '.controls' ).trigger( 'click' );
			} else {
				pauseAllExcept( $wrapper );
				switchTrack( itemIdx, true );
			}
		} );

		// -------------------------------------------------------------------
		// Buffer arc
		// -------------------------------------------------------------------
		$audio.on( 'progress', function () {
			if ( audio.buffered.length > 0 ) {
				setArcValue( $precache, audio.buffered.end( audio.buffered.length - 1 ), audio.duration );
			}
		} );

		// -------------------------------------------------------------------
		// Shuffle / loop / volume / drawer
		// -------------------------------------------------------------------
		initShuffleLoop( $wrapper,
			function () { return isShuffled; }, function ( v ) { isShuffled = v; },
			function () { return isLooped; },   function ( v ) { isLooped = v; }
		);
		initVolume( $wrapper, audio );
		initDrawerToggle( $wrapper );

		// -------------------------------------------------------------------
		// External pause (called by pauseAllExcept from another player)
		// -------------------------------------------------------------------
		$wrapper.data( 'cap-external-pause', function () {
			if ( ! audio.paused ) {
				audio.pause();
				detachTimeUpdate();
				resetSVG();
				if ( tracks[ currentIndex ] ) { setTrackBtn( tracks[ currentIndex ].$item, false ); }
			}
		} );

		// -------------------------------------------------------------------
		// Preload durations
		// -------------------------------------------------------------------
		if ( $trackItems.length ) { preloadDurations( $trackItems ); }
	}

	// -----------------------------------------------------------------------
	// FULL PLAYER
	// -----------------------------------------------------------------------

	function initFull( $wrapper ) {

		var $audio       = $wrapper.find( 'audio' );
		var audio        = $audio[ 0 ];
		var $trackItems  = $wrapper.find( '.cap-track-item' );
		var trackCount   = $trackItems.length;
		var $nowPlaying  = $wrapper.find( '.cap-now-playing' );
		var $btnPlay     = $wrapper.find( '.cap-btn-play' );
		var $btnPrev     = $wrapper.find( '.cap-btn-prev' );
		var $btnNext     = $wrapper.find( '.cap-btn-next' );
		var $seekbar     = $wrapper.find( '.cap-full-seekbar' );
		var $seekFill    = $wrapper.find( '.cap-full-seekbar-fill' );
		var $timeCurrent = $wrapper.find( '.cap-time-current' );
		var $timeTotal   = $wrapper.find( '.cap-time-total' );

		if ( ! audio ) { return; }

		// Build track data from DOM.
		var tracks = [];
		$trackItems.each( function () {
			tracks.push( { src: $( this ).data( 'src' ), title: $( this ).data( 'title' ), $item: $( this ) } );
		} );

		var currentIndex     = 0;
		var isPlaying        = false;
		var isShuffled       = false;
		var isLooped         = false;
		var timeUpdateHandler = null;

		// -------------------------------------------------------------------
		// Internal helpers
		// -------------------------------------------------------------------

		function bindTimeUpdate() {
			timeUpdateHandler = function () {
				if ( ! audio.duration ) { return; }
				$timeCurrent.text( formatTime( audio.currentTime ) );
				var pct = ( audio.currentTime / audio.duration ) * 100;
				$seekFill.css( 'width', pct + '%' );
				$seekbar.attr( 'aria-valuenow', Math.round( pct ) );
			};
			audio.addEventListener( 'timeupdate', timeUpdateHandler );
		}

		function detachTimeUpdate() {
			if ( timeUpdateHandler ) {
				audio.removeEventListener( 'timeupdate', timeUpdateHandler );
				timeUpdateHandler = null;
			}
		}

		function setPlayBtn( playing ) {
			$btnPlay.html( playing ? ICON.pause : ICON.play )
				.attr( 'aria-label', playing ? 'Pause' : 'Play' );
		}

		function resetPlayer() {
			detachTimeUpdate();
			isPlaying = false;
			setPlayBtn( false );
			$timeCurrent.text( '0:00' );
			$seekFill.css( 'width', '0%' );
			$seekbar.attr( 'aria-valuenow', 0 );
			$wrapper.removeClass( 'cap-is-playing' );
		}

		function updateActiveItem( newIdx ) {
			if ( tracks[ currentIndex ] ) {
				tracks[ currentIndex ].$item.removeClass( 'cap-track-active' ).attr( 'aria-pressed', 'false' );
			}
			currentIndex = newIdx;
			if ( tracks[ currentIndex ] ) {
				tracks[ currentIndex ].$item.addClass( 'cap-track-active' ).attr( 'aria-pressed', 'true' );
			}
		}

		function switchTrack( newIdx, autoPlay ) {
			audio.pause();
			resetPlayer();
			updateActiveItem( newIdx );

			audio.src = tracks[ currentIndex ].src;
			$nowPlaying.text( tracks[ currentIndex ].title );
			$timeTotal.text( '--:--' );

			// Update total duration once metadata loads.
			$audio.one( 'loadedmetadata', function () {
				$timeTotal.text( formatTime( audio.duration ) );
			} );

			if ( autoPlay ) {
				audio.play();
				isPlaying = true;
				bindTimeUpdate();
				audio.addEventListener( 'ended', onEnded );
				setPlayBtn( true );
				$wrapper.addClass( 'cap-is-playing' );
			}
		}

		function onEnded() {
			audio.removeEventListener( 'ended', onEnded );
			var n = nextIndex( currentIndex, trackCount, isShuffled, isLooped );
			if ( n !== -1 ) { switchTrack( n, true ); } else { resetPlayer(); }
		}

		// Populate initial total time once metadata for first track loads.
		$audio.one( 'loadedmetadata', function () {
			$timeTotal.text( formatTime( audio.duration ) );
		} );

		// -------------------------------------------------------------------
		// Play / Pause button
		// -------------------------------------------------------------------
		$btnPlay.on( 'click', function () {
			if ( isPlaying ) {
				audio.pause();
				detachTimeUpdate();
				isPlaying = false;
				setPlayBtn( false );
				$wrapper.removeClass( 'cap-is-playing' );
			} else {
				pauseAllExcept( $wrapper );
				audio.play();
				isPlaying = true;
				bindTimeUpdate();
				audio.addEventListener( 'ended', onEnded );
				setPlayBtn( true );
				$wrapper.addClass( 'cap-is-playing' );
			}
		} );

		// -------------------------------------------------------------------
		// Prev / Next
		// -------------------------------------------------------------------
		$btnPrev.on( 'click', function () {
			// If more than 3 seconds in, seek to start instead of going back.
			if ( audio.currentTime > 3 ) {
				audio.currentTime = 0;
				$timeCurrent.text( '0:00' );
				$seekFill.css( 'width', '0%' );
				return;
			}
			switchTrack( prevIndex( currentIndex, trackCount, isShuffled, isLooped ), isPlaying );
		} );

		$btnNext.on( 'click', function () {
			var n = nextIndex( currentIndex, trackCount, isShuffled, isLooped );
			switchTrack( n !== -1 ? n : 0, isPlaying );
		} );

		// -------------------------------------------------------------------
		// Seekbar click
		// -------------------------------------------------------------------
		$seekbar.on( 'click', function ( e ) {
			if ( ! audio.duration ) { return; }
			var rect = this.getBoundingClientRect();
			var pct  = Math.max( 0, Math.min( 1, ( e.clientX - rect.left ) / rect.width ) );
			audio.currentTime = pct * audio.duration;
			$seekFill.css( 'width', ( pct * 100 ) + '%' );
			$timeCurrent.text( formatTime( audio.currentTime ) );
		} );

		// -------------------------------------------------------------------
		// Track list items
		// -------------------------------------------------------------------
		$wrapper.on( 'click keydown', '.cap-track-item', function ( e ) {
			if ( e.type === 'keydown' && e.which !== 13 && e.which !== 32 ) { return; }
			if ( e.type === 'keydown' ) { e.preventDefault(); }
			var itemIdx = $trackItems.index( $( this ) );
			if ( itemIdx === currentIndex ) {
				$btnPlay.trigger( 'click' ); // Toggle play/pause.
			} else {
				pauseAllExcept( $wrapper );
				switchTrack( itemIdx, true );
			}
		} );

		// -------------------------------------------------------------------
		// Shuffle / loop / volume / drawer
		// -------------------------------------------------------------------
		initShuffleLoop( $wrapper,
			function () { return isShuffled; }, function ( v ) { isShuffled = v; },
			function () { return isLooped; },   function ( v ) { isLooped = v; }
		);
		initVolume( $wrapper, audio );
		initDrawerToggle( $wrapper );

		// -------------------------------------------------------------------
		// External pause
		// -------------------------------------------------------------------
		$wrapper.data( 'cap-external-pause', function () {
			if ( ! audio.paused ) {
				audio.pause();
				detachTimeUpdate();
				isPlaying = false;
				setPlayBtn( false );
				$wrapper.removeClass( 'cap-is-playing' );
			}
		} );

		// -------------------------------------------------------------------
		// Preload durations for the track list
		// -------------------------------------------------------------------
		if ( $trackItems.length ) { preloadDurations( $trackItems ); }
	}

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------
	$( function () {
		$( '.cap-player-wrapper' ).each( function ( i ) {
			var $w = $( this );
			if ( $w.hasClass( 'cap-player-full' ) ) {
				initFull( $w );
			} else {
				initMini( $w, i );
			}
		} );
	} );

} )( jQuery );
