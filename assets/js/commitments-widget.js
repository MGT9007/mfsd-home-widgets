/**
 * MFSD Home Widgets — Commitment Cards widget (7.0.0)
 * =====================================================
 * Spec: techspecs/HomeWidgets_TechSpec_v6_5_CHANGES.md §1, Commitments spec §12.
 *
 * Everything is loaded over the mfsd-commitments REST API after the page renders
 * (the home page may be cached). Requests go through window.MFSDCommitments.api(),
 * which fetches a fresh nonce first and retries once on a stale one.
 * "I did it!", "Update", "I saw it" and "Let's talk" all open the SHARED modal
 * from mfsd-commitments — never a copy.
 *
 * Modes (data-mode on .mfsd-hw-commit): student | parent | preview (admin, sample cards).
 * All text is inserted with textContent — no HTML from the API is ever injected.
 */
( function () {
  'use strict';

  var MC = window.MFSDCommitments;
  var TAB_KEY = 'mfsdHwCommitChild';
  var FEEL = { 1: '😞', 2: '😕', 3: '😐', 4: '🙂', 5: '🤩' };
  var VERDICT = { love: 'Love it', again: 'Try it again', junk: 'Junk it' };

  /* ── tiny DOM helper ─────────────────────────────────────────── */
  function el( tag, cls, text ) {
    var e = document.createElement( tag );
    if ( cls ) e.className = cls;
    if ( text !== undefined && text !== null ) e.textContent = text;
    return e;
  }
  function btn( cls, text, onClick ) {
    var b = el( 'button', cls, text );
    b.type = 'button';
    if ( onClick ) b.addEventListener( 'click', onClick );
    return b;
  }
  function store( key, val ) {
    try {
      if ( val === undefined ) return window.localStorage.getItem( key );
      window.localStorage.setItem( key, String( val ) );
    } catch ( e ) { /* private mode / blocked storage: just don't remember */ }
    return null;
  }
  function fmtDate( s ) {
    if ( ! s ) return '';
    var d = new Date( String( s ).replace( ' ', 'T' ) );
    return isNaN( d ) ? '' : d.toLocaleDateString( 'en-GB', { day: 'numeric', month: 'short' } );
  }

  /* ── sample data for the admin preview ───────────────────────── */
  var SAMPLE = [
    { id: 1, type: 'action', label: 'Wider Interests', kind: 'Spark', colour: '#F5A623', title: 'Try a beginner climbing session', status: 'open', status_line: '', waiting: false },
    { id: 2, type: 'action', label: 'Weekly RAG', kind: 'Red Plan', colour: '#E74C3C', title: 'Put my phone in the kitchen at 9pm', status: 'open', status_line: 'Red 2 weeks running', waiting: false },
    { id: 3, type: 'action', label: 'Dream & Junk Jobs', kind: 'Action', colour: '#4A90E2', title: 'Find out one real thing about being a vet', status: 'checked_in', status_line: 'Waiting for Mum to see it', waiting: true }
  ];

  function init( root ) {
    var mode        = root.getAttribute( 'data-mode' );
    var showDone    = root.getAttribute( 'data-show-done' ) === '1';
    var showTeasers = root.getAttribute( 'data-show-teasers' ) === '1';
    var body        = root.querySelector( '.mfsd-hw-commit__body' );
    var badge       = root.querySelector( '.mfsd-hw-commit__badge' );
    var childBar    = root.querySelector( '.mfsd-hw-commit__children' );
    var celebrate   = root.querySelector( '.mfsd-hw-commit__celebrate' );
    var doneCount   = root.querySelector( '.mfsd-hw-commit__done-count' );
    var tabs        = root.querySelectorAll( '.mfsd-hw-commit__tab' );
    var state       = { tab: 'open', child: 0, children: [], childName: '' };

    if ( mode !== 'preview' && ! MC ) {
      body.textContent = '';
      body.appendChild( el( 'p', 'mfsd-hw-commit__empty', 'Commitments are not available right now.' ) );
      return;
    }

    if ( window.location.hash === '#mfsd-commitments-done' && showDone ) state.tab = 'done';

    tabs.forEach( function ( t ) {
      t.addEventListener( 'click', function () {
        state.tab = t.getAttribute( 'data-tab' );
        syncTabs();
        load();
      } );
    } );

    function syncTabs() {
      tabs.forEach( function ( t ) {
        var on = t.getAttribute( 'data-tab' ) === state.tab;
        t.classList.toggle( 'is-on', on );
        t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
      } );
    }

    function setBadge( n ) {
      if ( mode === 'parent' && n > 0 ) {
        badge.textContent = n + ' waiting for you';
        badge.hidden = false;
      } else {
        badge.hidden = true;
      }
    }

    function setDone( n ) {
      if ( doneCount ) doneCount.textContent = n ? '(' + n + ')' : '';
    }

    function loading() {
      body.textContent = '';
      var sk = el( 'div', 'mfsd-hw-commit__skeleton' );
      sk.setAttribute( 'aria-hidden', 'true' );
      [ 'mfsd-hw-commit__skel-line mfsd-hw-commit__skel-line--short', 'mfsd-hw-commit__skel-line', 'mfsd-hw-commit__skel-line', 'mfsd-hw-commit__skel-btn' ]
        .forEach( function ( c ) { sk.appendChild( el( 'div', c ) ); } );
      body.appendChild( sk );
    }

    function failed() {
      body.textContent = '';
      var box = el( 'div', 'mfsd-hw-commit__empty' );
      box.appendChild( el( 'p', '', 'We couldn\'t load the cards just now.' ) );
      box.appendChild( btn( 'mfsd-hw-commit__btn mfsd-hw-commit__btn--ghost', 'Try again', load ) );
      body.appendChild( box );
      fit();
    }

    function fit() {
      if ( typeof window.mfsdHwFitGrid === 'function' ) window.mfsdHwFitGrid();
    }

    /* ── card rendering ────────────────────────────────────────── */

    function cardShell( c ) {
      var card = el( 'article', 'mfsd-hw-commit__card' );
      card.style.setProperty( '--mfsd-commit-colour', c.colour || '#8A8F98' );
      if ( c.reminded && mode === 'parent' && c.waiting ) card.classList.add( 'is-reminded' );
      var top = el( 'div', 'mfsd-hw-commit__card-top' );
      if ( c.icon ) {
        var img = el( 'img', 'mfsd-hw-commit__icon' );
        img.src = c.icon; img.alt = '';
        top.appendChild( img );
      }
      top.appendChild( el( 'span', 'mfsd-hw-commit__kind', c.kind || 'Action' ) );
      if ( c.label ) top.appendChild( el( 'span', 'mfsd-hw-commit__source', c.label ) );
      card.appendChild( top );
      card.appendChild( el( 'h4', 'mfsd-hw-commit__card-title', c.title ) );
      return card;
    }

    function studentCard( c ) {
      var card = cardShell( c );
      if ( c.status_line ) card.appendChild( el( 'p', 'mfsd-hw-commit__status', c.status_line ) );
      if ( c.type === 'target' && c.progress_pct !== null && c.progress_pct !== undefined && c.status !== 'checked_in' ) {
        var bar = el( 'div', 'mfsd-hw-commit__progress' );
        var fill = el( 'span' );
        fill.style.width = Math.max( 0, Math.min( 100, c.progress_pct ) ) + '%';
        bar.appendChild( fill );
        bar.setAttribute( 'aria-label', c.progress_pct + '% done' );
        card.appendChild( bar );
      }
      var actions = el( 'div', 'mfsd-hw-commit__actions' );
      if ( mode === 'preview' ) {
        actions.appendChild( btn( 'mfsd-hw-commit__btn', c.waiting ? 'Preview only' : 'I did it!' ) );
      } else if ( c.status === 'checked_in' ) {
        if ( c.can_remind ) {
          actions.appendChild( btn( 'mfsd-hw-commit__link', 'Remind ' + ( c.parent_name || 'your parent' ), function ( e ) {
            e.target.disabled = true;
            MC.remind( c.id ).then( load ).catch( function () { e.target.disabled = false; } );
          } ) );
        }
        if ( c.talk_requested ) {
          actions.appendChild( btn( 'mfsd-hw-commit__link', 'Change to "not yet"', function ( e ) {
            e.target.disabled = true;
            MC.setStatus( c.id, 'not_yet' ).then( load ).catch( function () { e.target.disabled = false; } );
          } ) );
        }
      } else {
        actions.appendChild( btn( 'mfsd-hw-commit__btn', c.type === 'target' ? 'Update' : 'I did it!', function () {
          MC.openCheckin( c, { onDone: load } );
        } ) );
      }
      if ( actions.childNodes.length ) card.appendChild( actions );
      return card;
    }

    function parentCard( c ) {
      var card = cardShell( c );
      if ( c.status_line ) card.appendChild( el( 'p', 'mfsd-hw-commit__status', c.status_line ) );
      if ( c.checkin && ( c.checkin.feeling || c.checkin.verdict ) ) {
        var bits = [];
        if ( c.checkin.feeling ) bits.push( FEEL[ c.checkin.feeling ] || '' );
        if ( c.checkin.verdict ) bits.push( VERDICT[ c.checkin.verdict ] || '' );
        card.appendChild( el( 'p', 'mfsd-hw-commit__meta', bits.join( ' · ' ) ) );
      }
      if ( c.checkin && c.checkin.text ) card.appendChild( el( 'blockquote', 'mfsd-hw-commit__quote', c.checkin.text ) );
      if ( c.prompt ) card.appendChild( el( 'p', 'mfsd-hw-commit__prompt', c.prompt ) );
      if ( c.waiting ) {
        var actions = el( 'div', 'mfsd-hw-commit__actions' );
        actions.appendChild( btn( 'mfsd-hw-commit__btn', c.talk_requested ? 'I saw it' : 'I saw it / Let\'s talk', function () {
          MC.openValidate( c, { onDone: load } );
        } ) );
        card.appendChild( actions );
      }
      return card;
    }

    function doneCard( c ) {
      var card = cardShell( c );
      card.classList.add( 'is-done' );
      var line = [];
      if ( c.validated_at ) line.push( fmtDate( c.validated_at ) );
      line.push( c.status_line || 'Done' );
      if ( c.coins ) line.push( '+' + c.coins + ' coins' );
      card.appendChild( el( 'p', 'mfsd-hw-commit__status', line.join( ' · ' ) ) );
      if ( c.validator_note ) card.appendChild( el( 'blockquote', 'mfsd-hw-commit__quote', c.validator_note ) );
      return card;
    }

    function teaserCard( t ) {
      var card = el( 'article', 'mfsd-hw-commit__card is-teaser' );
      var who = state.childName || 'Your child';
      card.appendChild( el( 'span', 'mfsd-hw-commit__kind', t.label || 'New commitment' ) );
      card.appendChild( el( 'h4', 'mfsd-hw-commit__card-title', who + ' has made a commitment in ' + ( t.label || 'a task' ) + '.' ) );
      if ( t.url ) {
        var a = el( 'a', 'mfsd-hw-commit__link', t.source_task === 'junk_jobs' ? 'Play the guessing game to see it →' : 'Open ' + ( t.label || 'the task' ) + ' to see it →' );
        a.href = t.url + ( t.url.indexOf( 'student_id=' ) === -1 && state.child ? ( t.url.indexOf( '?' ) === -1 ? '?' : '&' ) + 'student_id=' + state.child : '' );
        card.appendChild( a );
      }
      return card;
    }

    /* ── carousel ──────────────────────────────────────────────── */

    function carousel( nodes ) {
      var wrap = el( 'div', 'mfsd-hw-commit__carousel mfsd-hw-carousel' );
      wrap.setAttribute( 'data-autoplay', '0' );   // never auto-rotate this widget (spec §1.5)
      wrap.setAttribute( 'tabindex', '0' );
      wrap.setAttribute( 'aria-roledescription', 'carousel' );
      nodes.forEach( function ( n, i ) {
        var s = el( 'div', 'mfsd-hw-carousel__slide' + ( i === 0 ? ' mfsd-hw-carousel__slide--active' : '' ) );
        s.appendChild( n );
        wrap.appendChild( s );
      } );
      if ( nodes.length > 1 ) {
        var nav = el( 'div', 'mfsd-hw-commit__nav' );
        var prev = btn( 'mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev', '‹' );
        prev.setAttribute( 'aria-label', 'Previous card' );
        var next = btn( 'mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next', '›' );
        next.setAttribute( 'aria-label', 'Next card' );
        nav.appendChild( prev );
        if ( nodes.length <= 8 ) {
          var dots = el( 'div', 'mfsd-hw-carousel__dots' );
          nodes.forEach( function ( n, i ) {
            var d = btn( 'mfsd-hw-carousel__dot' + ( i === 0 ? ' mfsd-hw-carousel__dot--active' : '' ), '' );
            d.setAttribute( 'aria-label', 'Card ' + ( i + 1 ) );
            dots.appendChild( d );
          } );
          nav.appendChild( dots );
        } else {
          nav.appendChild( el( 'span', 'mfsd-hw-carousel__counter', '1 / ' + nodes.length ) );
        }
        nav.appendChild( next );
        wrap.appendChild( nav );
      }
      return wrap;
    }

    function show( nodes, emptyText ) {
      body.textContent = '';
      if ( ! nodes.length ) {
        body.appendChild( el( 'p', 'mfsd-hw-commit__empty', emptyText ) );
      } else {
        var c = carousel( nodes );
        body.appendChild( c );
        if ( typeof window.mfsdHwInitCarousel === 'function' ) window.mfsdHwInitCarousel( c );
      }
      fit();
    }

    /* ── loaders ───────────────────────────────────────────────── */

    function loadStudent() {
      if ( state.tab === 'done' ) {
        return MC.api( 'GET', '/done' ).then( function ( r ) {
          setDone( r.done_count || 0 );
          show( ( r.cards || [] ).map( doneCard ), 'Nothing here yet. Cards move here once they\'re seen.' );
        } );
      }
      return MC.api( 'GET', '' ).then( function ( r ) {
        setDone( r.done_count || 0 );
        show( ( r.cards || [] ).map( studentCard ), 'No commitments right now. Your next task might give you one!' );
        showCelebration( r.celebrate || [] );
      } );
    }

    function showCelebration( list ) {
      if ( ! list.length ) { celebrate.hidden = true; return; }
      var c = list[ 0 ];
      celebrate.textContent = '';
      var box = el( 'div', 'mfsd-hw-commit__celebrate-box' );
      box.appendChild( el( 'span', 'mfsd-hw-commit__coin', '🪙' ) );
      var who = c.auto_validated ? 'Confirmed' : ( c.status_line || 'Seen' );
      box.appendChild( el( 'strong', '', who + '! "' + c.title + '"' + ( c.coins ? ' +' + c.coins + ' coins' : '' ) ) );
      if ( c.validator_note ) box.appendChild( el( 'p', '', '"' + c.validator_note + '"' ) );
      if ( list.length > 1 ) box.appendChild( el( 'p', '', 'And ' + ( list.length - 1 ) + ' more in your Done tab.' ) );
      box.appendChild( btn( 'mfsd-hw-commit__link', 'Nice!', function () { celebrate.hidden = true; } ) );
      celebrate.appendChild( box );
      celebrate.hidden = false;
    }

    function loadParent() {
      var first = state.children.length ? Promise.resolve( { children: state.children } ) : MC.api( 'GET', '/children' );
      return first.then( function ( r ) {
        state.children = r.children || [];
        if ( ! state.children.length ) {
          body.textContent = '';
          body.appendChild( el( 'p', 'mfsd-hw-commit__empty', 'Link your child\'s account to see their commitments here.' ) );
          fit();
          return;
        }
        var saved = parseInt( store( TAB_KEY ) || '0', 10 );
        if ( ! state.child ) {
          state.child = state.children.some( function ( c ) { return c.id === saved; } ) ? saved : state.children[ 0 ].id;
        }
        renderChildTabs();
        var path = '/child/' + state.child + ( state.tab === 'done' ? '/done' : '' );
        return MC.api( 'GET', path ).then( function ( d ) {
          if ( state.tab === 'done' ) {
            show( ( d.cards || [] ).map( doneCard ), 'Nothing confirmed yet.' );
            return;
          }
          state.childName = ( d.child && d.child.first_name ) || state.childName;
          setBadge( d.waiting || 0 );
          setDone( d.done_count || 0 );
          updateChildCount( state.child, d.waiting || 0 );
          var nodes = ( d.cards || [] ).map( parentCard );
          if ( showTeasers ) nodes = nodes.concat( ( d.teasers || [] ).map( teaserCard ) );
          show( nodes, 'No commitments yet. When ' + ( state.childName || 'your child' ) + ' makes one in a task, it shows here.' );
        } );
      } );
    }

    function renderChildTabs() {
      if ( state.children.length < 2 ) { childBar.hidden = true; return; }
      childBar.textContent = '';
      state.children.forEach( function ( c ) {
        var t = btn( 'mfsd-hw-commit__child' + ( c.id === state.child ? ' is-on' : '' ), c.first_name, function () {
          state.child = c.id;
          state.childName = c.first_name;
          store( TAB_KEY, c.id );
          load();
        } );
        t.setAttribute( 'role', 'tab' );
        t.setAttribute( 'aria-selected', c.id === state.child ? 'true' : 'false' );
        t.setAttribute( 'data-child', c.id );
        if ( c.waiting ) t.appendChild( el( 'span', 'mfsd-hw-commit__count', String( c.waiting ) ) );
        if ( c.id === state.child ) state.childName = c.first_name;
        childBar.appendChild( t );
      } );
      childBar.hidden = false;
    }

    function updateChildCount( id, n ) {
      state.children.forEach( function ( c ) { if ( c.id === id ) c.waiting = n; } );
      var t = childBar.querySelector( '[data-child="' + id + '"] .mfsd-hw-commit__count' );
      if ( t ) { t.textContent = String( n ); t.hidden = ! n; }
    }

    function loadPreview() {
      setDone( 12 );
      show( state.tab === 'done' ? [] : SAMPLE.map( studentCard ), 'Preview: the Done shelf shows cards a parent has confirmed.' );
    }

    function load() {
      syncTabs();
      if ( mode === 'preview' ) { loadPreview(); return; }
      loading();
      var p = mode === 'parent' ? loadParent() : loadStudent();
      p.catch( failed );
    }

    load();
  }

  function boot() {
    document.querySelectorAll( '.mfsd-hw-commit' ).forEach( init );
  }
  if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
  else boot();
} )();
