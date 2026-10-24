/**
 * MFSD Home Widgets — News Carousel
 * ===================================
 * Auto-rotates news slides every 5 seconds.
 * Left/right arrows for manual navigation.
 * Dot indicators show position.
 * Pauses auto-rotation on hover, resumes on mouse leave.
 *
 * Only initialises on carousels with 2+ slides (single-item widgets
 * have no controls and no carousel class).
 *
 * 7.0.0:
 *  - data-autoplay="0" on a carousel turns auto-rotation off completely
 *    (including after arrow/dot clicks). Default (no attribute) is unchanged.
 *  - window.mfsdHwInitCarousel( el ) initialises a carousel added after page
 *    load (e.g. the Commitment Cards widget, which loads its cards over REST).
 *  - window.mfsdHwFitGrid() re-runs the grid height fit after late content.
 *  - Left/right arrow keys move a focused carousel.
 *  - An optional .mfsd-hw-carousel__counter element shows "3 / 11".
 */

( function() {
  'use strict';

  // ── Grid height: fill from grid top to viewport bottom ─────────────────────
  // flex:1 on the grid fights an explicit height, so we remove the grid from
  // flex sizing entirely and set an explicit height directly.
  function fitGrid() {
    var grid = document.querySelector( '.mfsd-hw-grid' );
    if ( ! grid ) return;
    grid.style.flex   = '';
    grid.style.height = '';
    var top       = grid.getBoundingClientRect().top;
    var available = Math.floor( window.innerHeight - top );
    if ( available > 100 ) {
      grid.style.flex   = 'none';
      grid.style.height = available + 'px';
    }
  }

  window.mfsdHwFitGrid = fitGrid;

  document.addEventListener( 'DOMContentLoaded', fitGrid );
  window.addEventListener( 'load', fitGrid );
  window.addEventListener( 'resize', fitGrid );

  // ── Carousel init ───────────────────────────────────────────────────────────

  function initCarousel( carousel ) {
    if ( ! carousel || carousel.getAttribute( 'data-hw-init' ) === '1' ) return;

    var slides   = carousel.querySelectorAll( '.mfsd-hw-carousel__slide' );
    var dots     = carousel.querySelectorAll( '.mfsd-hw-carousel__dot' );
    var counter  = carousel.querySelector( '.mfsd-hw-carousel__counter' );
    var btnPrev  = carousel.querySelector( '.mfsd-hw-carousel__arrow--prev' );
    var btnNext  = carousel.querySelector( '.mfsd-hw-carousel__arrow--next' );
    var total    = slides.length;
    var current  = 0;
    var timer    = null;
    var INTERVAL = 5000;
    var autoplay = carousel.getAttribute( 'data-autoplay' ) !== '0';

    if ( total < 2 ) return;
    carousel.setAttribute( 'data-hw-init', '1' );

    function show( index ) {
      current = ( index + total ) % total;
      slides.forEach( function( s, i ) {
        s.classList.toggle( 'mfsd-hw-carousel__slide--active', i === current );
        s.setAttribute( 'aria-hidden', i === current ? 'false' : 'true' );
      });
      dots.forEach( function( d, i ) {
        d.classList.toggle( 'mfsd-hw-carousel__dot--active', i === current );
      });
      if ( counter ) counter.textContent = ( current + 1 ) + ' / ' + total;
    }

    function next() { show( current + 1 ); }
    function prev() { show( current - 1 ); }

    function startAuto() {
      stopAuto();
      if ( ! autoplay ) return;
      timer = setInterval( next, INTERVAL );
    }

    function stopAuto() {
      if ( timer ) { clearInterval( timer ); timer = null; }
    }

    // Arrow clicks
    if ( btnNext ) btnNext.addEventListener( 'click', function() { next(); startAuto(); } );
    if ( btnPrev ) btnPrev.addEventListener( 'click', function() { prev(); startAuto(); } );

    // Dot clicks
    dots.forEach( function( dot, i ) {
      dot.addEventListener( 'click', function() { show( i ); startAuto(); } );
    });

    // Left/right keys when the carousel (or something inside it) has focus
    carousel.addEventListener( 'keydown', function( e ) {
      var tag = ( e.target && e.target.tagName ) || '';
      if ( tag === 'INPUT' || tag === 'TEXTAREA' ) return;
      if ( e.key === 'ArrowRight' ) { next(); startAuto(); e.preventDefault(); }
      if ( e.key === 'ArrowLeft' )  { prev(); startAuto(); e.preventDefault(); }
    } );

    // Pause on hover, resume on leave
    carousel.addEventListener( 'mouseenter', stopAuto );
    carousel.addEventListener( 'mouseleave', startAuto );

    // Swipe support for mobile
    var touchStartX = 0;
    carousel.addEventListener( 'touchstart', function( e ) {
      touchStartX = e.changedTouches[0].screenX;
      stopAuto();
    }, { passive: true } );

    carousel.addEventListener( 'touchend', function( e ) {
      var diff = e.changedTouches[0].screenX - touchStartX;
      if ( Math.abs( diff ) > 50 ) {
        diff > 0 ? prev() : next();
      }
      startAuto();
    }, { passive: true } );

    // Initial state
    show( 0 );
    startAuto();
  }

  window.mfsdHwInitCarousel = initCarousel;

  document.querySelectorAll( '.mfsd-hw-carousel' ).forEach( initCarousel );

  // ── SteveGPT Help Bot — collapsible toggle ─────────────────────────────────
  // No-op on any page that has no .mfsd-hw-card__steve-toggle elements.

  document.querySelectorAll( '.mfsd-hw-card__steve-toggle' ).forEach( function( btn ) {
    btn.addEventListener( 'click', function() {
      var targetId = btn.getAttribute( 'aria-controls' );
      var panel    = document.getElementById( targetId );
      if ( ! panel ) return;
      var isHidden = panel.classList.contains( 'mfsd-hw-card__steve-body--hidden' );
      panel.classList.toggle( 'mfsd-hw-card__steve-body--hidden', ! isHidden );
      btn.setAttribute( 'aria-expanded', isHidden ? 'true' : 'false' );
      btn.textContent = isHidden ? 'Close Steve ✕' : 'Ask Steve →';
    } );
  } );

} )();
