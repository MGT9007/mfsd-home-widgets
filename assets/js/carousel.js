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
 */

( function() {
  'use strict';

  document.querySelectorAll( '.mfsd-hw-carousel' ).forEach( function( carousel ) {
    var slides  = carousel.querySelectorAll( '.mfsd-hw-carousel__slide' );
    var dots    = carousel.querySelectorAll( '.mfsd-hw-carousel__dot' );
    var btnPrev = carousel.querySelector( '.mfsd-hw-carousel__arrow--prev' );
    var btnNext = carousel.querySelector( '.mfsd-hw-carousel__arrow--next' );
    var total   = slides.length;
    var current = 0;
    var timer   = null;
    var INTERVAL = 5000;

    if ( total < 2 ) return;

    function show( index ) {
      current = ( index + total ) % total;
      slides.forEach( function( s, i ) {
        s.classList.toggle( 'mfsd-hw-carousel__slide--active', i === current );
      });
      dots.forEach( function( d, i ) {
        d.classList.toggle( 'mfsd-hw-carousel__dot--active', i === current );
      });
    }

    function next() { show( current + 1 ); }
    function prev() { show( current - 1 ); }

    function startAuto() {
      stopAuto();
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
  });

} )();
