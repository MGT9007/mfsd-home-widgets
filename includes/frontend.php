<?php
/**
 * MFSD Home Widgets — Frontend Rendering
 *
 * Renders all active widget instances visible to the current role,
 * in sort_order sequence, in a 3-column CSS grid.
 *
 * Version: 3.5.0 — Badge now derived from latest task slug (in sync); Who Am I composite rendering; solution_lens badge added.
 */

defined( 'ABSPATH' ) || exit;


// ─── GRID RENDERER ────────────────────────────────────────────────────────────

add_action( 'mfsd_home_widgets', 'mfsd_hw_render_grid' );

if ( ! function_exists( 'mfsd_hw_render_grid' ) ) :
function mfsd_hw_render_grid(): void {
    if ( ! is_user_logged_in() ) return;

    $role    = function_exists( 'mfsd_get_user_role' ) ? mfsd_get_user_role() : mfsd_hw_role_fallback();
    $widgets = mfsd_hw_get_for_role( $role );

    if ( empty( $widgets ) ) {
        echo '<p class="mfsd-hw-empty">' . esc_html__( 'No widgets configured.', 'mfsd-home-widgets' ) . '</p>';
        return;
    }

    $count   = count( $widgets );
    $layout  = mfsd_hw_get_layout_for_role( $role );

    // Determine the CSS modifier class.
    // For 7 widgets, honour the chosen layout (7 = Layout A, 7b = Layout B).
    // For 6 widgets, honour the chosen layout (6 = Layout A, 6b = Layout B).
    // For other counts use automatic sizing classes.
    if ( $count === 7 ) {
        $mod_class = ' mfsd-hw-grid--' . ( in_array( $layout, [ '7b', '7c' ], true ) ? $layout : '7' );
    } elseif ( $count === 6 ) {
        $mod_class = ' mfsd-hw-grid--' . ( in_array( $layout, [ '6b', '6c' ], true ) ? $layout : '6' );
    } elseif ( in_array( $count, [ 1, 2, 4 ], true ) ) {
        $mod_class = ' mfsd-hw-grid--' . $count;
    } else {
        $mod_class = '';
    }

    echo '<div class="mfsd-hw-grid' . esc_attr( $mod_class ) . '">';
    foreach ( $widgets as $idx => $w ) {
        $cell_mod = $mod_class ? ' mfsd-hw-grid__cell--' . ( $idx + 1 ) : '';
        echo '<div class="mfsd-hw-grid__cell' . esc_attr( $cell_mod ) . '">';
        mfsd_hw_render_widget( $w['type'], (array) $w['config'], $role );
        echo '</div>';
    }
    echo '</div>';
}
endif; // mfsd_hw_render_grid

if ( ! function_exists( 'mfsd_hw_role_fallback' ) ) :
function mfsd_hw_role_fallback(): string {
    $user  = wp_get_current_user();
    $roles = (array) $user->roles;
    if ( in_array( 'administrator', $roles, true ) ) return 'admin';
    if ( in_array( 'teacher',       $roles, true ) ) return 'teacher';
    if ( in_array( 'parent',        $roles, true ) ) return 'parent';
    if ( in_array( 'student',       $roles, true ) ) return 'student';
    return 'parent';
}
endif; // mfsd_hw_role_fallback

if ( ! function_exists( 'mfsd_hw_get_layout_for_role' ) ) :
/**
 * Return the chosen layout slug for a role.
 * Stored as a JSON object in wp_options: { "student": "7b", "parent": "7", ... }
 * Valid values: '7' (Layout A) or '7b' (Layout B). Defaults to '7'.
 */
function mfsd_hw_get_layout_for_role( string $role ): string {
    $layouts = get_option( 'mfsd_hw_role_layouts', [] );
    if ( ! is_array( $layouts ) ) $layouts = [];
    $val = $layouts[ $role ] ?? '7';
    return in_array( $val, [ '7', '7b', '7c', '6', '6b', '6c' ], true ) ? $val : '7';
}
endif; // mfsd_hw_get_layout_for_role

function mfsd_hw_render_widget( string $type, array $config, string $role ): void {
    switch ( $type ) {
        case 'news_internal': mfsd_hw_card_news( 'internal', $config ); break;
        case 'news_external': mfsd_hw_card_news( 'external', $config ); break;
        case 'shorts':        mfsd_hw_card_shorts( $config );            break;
        case 'new_courses':   mfsd_hw_card_courses( $config );           break;
        case 'top_scores':    mfsd_hw_card_scores( $config, $role );     break;
        case 'progress':      mfsd_hw_card_progress( $config, $role );   break;
        case 'rss_feed':      mfsd_hw_card_rss( $config );               break;
    }
}


// ─── HELPER: get linked student ID for a parent ──────────────────────────────
// Centralised so both progress and scores widgets use the same correct query.

function mfsd_hw_get_linked_student_id( int $parent_user_id ): int {
    global $wpdb;

    $links_table = $wpdb->prefix . 'mfsd_parent_student_links';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$links_table}'" ) !== $links_table ) {
        return 0;
    }

    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT student_user_id
         FROM {$links_table}
         WHERE parent_user_id = %d
           AND link_status = 'active'
         ORDER BY is_primary_contact DESC
         LIMIT 1",
        $parent_user_id
    ) );
}


// ─── CARD: News (internal + external) ─────────────────────────────────────────
// Full-bleed background image with gradient overlay and text on top.
// Supports up to 10 articles in a rotating carousel.
// Single-item configs render without carousel controls.
//
// v4.0.0 — Carousel support with auto-rotation, arrows, dots.

function mfsd_hw_card_news( string $variant, array $c ): void {
    $title    = $variant === 'internal' ? 'MFS NEWS' : 'COMMUNITY ARTICLES';
    $icon     = $variant === 'internal' ? '📣' : '📰';
    $external = $variant === 'external';

    // ── Backward compatibility: flat config → items array ────────────────────
    if ( isset( $c['items'] ) && is_array( $c['items'] ) ) {
        $items = $c['items'];
    } else {
        $items = [ [
            'headline' => $c['headline'] ?? '',
            'summary'  => $c['summary']  ?? '',
            'image_id' => $c['image_id'] ?? 0,
            'link'     => $c['link']     ?? '',
            'cta_text' => $c['cta_text'] ?? 'Read More',
        ] ];
    }

    $items = array_values( array_filter( $items, function( $item ) {
        return ! empty( $item['headline'] ) || ! empty( $item['image_id'] );
    } ) );

    if ( empty( $items ) ) return;

    $count       = count( $items );
    $is_carousel = $count > 1;
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--news-hero">

      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon"><?php echo $icon; ?></span>
        <?php echo esc_html( $title ); ?>
      </div>

      <div class="mfsd-hw-card__news-body<?php echo $is_carousel ? ' mfsd-hw-carousel' : ''; ?>">

        <?php foreach ( $items as $i => $item ) :
            $img      = mfsd_hw_get_image_url( (int) ( $item['image_id'] ?? 0 ) );
            $link     = $item['link'] ?? '';
            $cta_text = $item['cta_text'] ?? 'Read More';
            $active   = $i === 0 ? ' mfsd-hw-carousel__slide--active' : '';
        ?>
          <div class="mfsd-hw-carousel__slide<?php echo $active; ?>">
            <div class="mfsd-hw-card__hero-panel">
              <img class="mfsd-hw-card__hero-img"
                   src="<?php echo esc_url( $img ); ?>"
                   alt="<?php echo esc_attr( $item['headline'] ?? '' ); ?>">
            </div>
            <div class="mfsd-hw-card__hero-content">
              <h3 class="mfsd-hw-card__hero-headline">
                <?php echo esc_html( $item['headline'] ?? '' ); ?>
              </h3>
              <?php if ( ! empty( $item['summary'] ) ) : ?>
                <p class="mfsd-hw-card__hero-summary">
                  <?php echo esc_html( $item['summary'] ); ?>
                </p>
              <?php endif; ?>
              <?php if ( ! empty( $link ) ) : ?>
                <a href="<?php echo esc_url( $link ); ?>"
                   class="mfsd-hw-card__cta"
                   <?php echo $external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                  <?php echo esc_html( $cta_text ); ?> →
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if ( $is_carousel ) : ?>
          <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="Previous">‹</button>
          <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="Next">›</button>
        <?php endif; ?>

      </div>

    </div>
    <?php
}


// ─── CARD: Shorts Video ──────────────────────────────────────────────────────

function mfsd_hw_card_shorts( array $c ): void {
    $img = mfsd_hw_get_image_url( (int) ( $c['image_id'] ?? 0 ) );
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--shorts">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon">🎬</span>
        SHORTS VIDEO
      </div>
      <div class="mfsd-hw-card__body">
        <div class="mfsd-hw-card__media-row">
          <div class="mfsd-hw-card__video-thumb">
            <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $c['title'] ?? '' ); ?>">
            <span class="mfsd-hw-card__play">▶</span>
            <?php if ( ! empty( $c['duration'] ) ) : ?>
              <span class="mfsd-hw-card__duration"><?php echo esc_html( $c['duration'] ); ?></span>
            <?php endif; ?>
          </div>
          <div class="mfsd-hw-card__text">
            <h3 class="mfsd-hw-card__headline"><?php echo esc_html( $c['title'] ?? '' ); ?></h3>
          </div>
        </div>
      </div>
      <?php if ( ! empty( $c['video_url'] ) ) : ?>
        <a href="<?php echo esc_url( $c['video_url'] ); ?>" class="mfsd-hw-card__cta" target="_blank" rel="noopener noreferrer">
          <?php echo esc_html( $c['cta_text'] ?? 'Watch Now' ); ?>
        </a>
      <?php endif; ?>
    </div>
    <?php
}


// ─── CARD: New Course ────────────────────────────────────────────────────────

function mfsd_hw_card_courses( array $c ): void {
    $img = mfsd_hw_get_image_url( (int) ( $c['image_id'] ?? 0 ) );
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--courses">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon">🎓</span>
        NEW COURSE
      </div>
      <div class="mfsd-hw-card__body"<?php if ( $img ) : ?> style="--hw-badge-bg: url('<?php echo esc_url( $img ); ?>')"<?php endif; ?>>
        <h3 class="mfsd-hw-card__headline"><?php echo esc_html( $c['headline'] ?? '' ); ?></h3>
        <p class="mfsd-hw-card__summary"><?php echo esc_html( $c['summary'] ?? '' ); ?></p>
      </div>
      <?php if ( ! empty( $c['link'] ) ) : ?>
        <a href="<?php echo esc_url( $c['link'] ); ?>" class="mfsd-hw-card__cta">
          <?php echo esc_html( $c['cta_text'] ?? 'Course Details' ); ?>
        </a>
      <?php endif; ?>
    </div>
    <?php
}


// ─── HELPER: find the arcade lobby page URL ───────────────────────────────────

function mfsd_hw_get_arcade_page_url(): string {
    global $wpdb;
    $page_id = $wpdb->get_var(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_status = 'publish'
           AND post_type IN ('page','post')
           AND post_content LIKE '%[mfsd_arcade%'
         LIMIT 1"
    );
    if ( $page_id ) {
        $url = get_permalink( (int) $page_id );
        return $url ? rtrim( $url, '/' ) . '/' : '';
    }
    $page = get_page_by_path( 'arcade' );
    if ( $page ) {
        return get_permalink( $page->ID ) ?: home_url( '/arcade/' );
    }
    return home_url( '/arcade/' );
}


// ─── CARD: Top Scores / Leaderboard ──────────────────────────────────────────

function mfsd_hw_card_scores( array $c, string $role ): void {
    global $wpdb;

    $limit       = (int) ( $c['score_count'] ?? 5 );
    $mode        = $c['mode'] ?? 'global';
    $is_student  = $role === 'student';
    $is_parent   = in_array( $role, [ 'parent', 'teacher' ], true );
    $current_uid = get_current_user_id();
    $title       = ( $is_parent && $mode === 'student' ) ? "MY STUDENT'S SCORES" : 'TOP SCORES';

    $scores_table = $wpdb->prefix . 'mfsd_arcade_scores';
    $games_table  = $wpdb->prefix . 'mfsd_arcade_games';

    // Who to highlight with "YOU" — current student or linked student for parents.
    $highlight_uid = $current_uid;
    if ( $is_parent ) {
        $linked = mfsd_hw_get_linked_student_id( $current_uid );
        if ( $linked ) $highlight_uid = $linked;
    }

    $games_data  = [];
    $has_scores  = $wpdb->get_var( "SHOW TABLES LIKE '{$scores_table}'" ) === $scores_table;
    $has_g_table = $wpdb->get_var( "SHOW TABLES LIKE '{$games_table}'" ) === $games_table;

    if ( $has_scores ) {
        // ── Get game list ──────────────────────────────────────────────────
        if ( $has_g_table ) {
            if ( $is_student || $is_parent ) {
                // Only games the highlighted student has played.
                $games = $wpdb->get_results( $wpdb->prepare(
                    "SELECT g.slug, g.title, g.category
                     FROM {$games_table} g
                     INNER JOIN {$scores_table} s ON s.game_slug = g.slug AND s.student_id = %d
                     WHERE g.active = 1
                     GROUP BY g.slug
                     ORDER BY g.sort_order ASC",
                    $highlight_uid
                ), ARRAY_A ) ?: [];
            } else {
                // Global: all active games that have at least one score.
                $games = $wpdb->get_results(
                    "SELECT g.slug, g.title, g.category
                     FROM {$games_table} g
                     INNER JOIN {$scores_table} s ON s.game_slug = g.slug
                     WHERE g.active = 1
                     GROUP BY g.slug
                     ORDER BY g.sort_order ASC"
                , ARRAY_A ) ?: [];
            }
        } else {
            // Fallback: derive from scores table only (no titles available).
            if ( $is_student || $is_parent ) {
                $slugs = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT game_slug FROM {$scores_table} WHERE student_id = %d ORDER BY game_slug ASC",
                    $highlight_uid
                ) ) ?: [];
            } else {
                $slugs = $wpdb->get_col( "SELECT DISTINCT game_slug FROM {$scores_table} ORDER BY game_slug ASC" ) ?: [];
            }
            $games = array_map( fn( $s ) => [
                'slug'     => $s,
                'title'    => ucwords( str_replace( [ '-', '_' ], ' ', $s ) ),
                'category' => '',
            ], $slugs );
        }

        // ── Per-game leaderboard ───────────────────────────────────────────
        foreach ( $games as $game ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.student_id, s.score, s.initials, u.display_name
                 FROM {$scores_table} s
                 LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
                 WHERE s.game_slug = %s
                 ORDER BY s.score DESC
                 LIMIT %d",
                $game['slug'], $limit
            ), ARRAY_A ) ?: [];

            if ( ! empty( $rows ) ) {
                $games_data[] = [
                    'slug'     => $game['slug'],
                    'title'    => $game['title'],
                    'category' => $game['category'] ?? '',
                    'rows'     => $rows,
                ];
            }
        }
    }

    $slide_count = count( $games_data );

    // ── Append latest arcade game as a promo tile if student hasn't played it ─
    $latest_game_promo = null;
    $arcade_page_url   = '';
    if ( ( $is_student || $is_parent ) && $has_g_table && ! empty( $games_data ) ) {
        $latest_game = $wpdb->get_row(
            "SELECT title, slug, description, category, thumbnail_url, min_coins
             FROM {$games_table}
             WHERE active = 1
             ORDER BY id DESC LIMIT 1",
            ARRAY_A
        );
        if ( $latest_game ) {
            $played_slugs = array_column( $games_data, 'slug' );
            if ( ! in_array( $latest_game['slug'], $played_slugs, true ) ) {
                $latest_game_promo = $latest_game;
                $arcade_page_url   = mfsd_hw_get_arcade_page_url();
                $slide_count++;
            }
        }
    }

    $is_carousel = $slide_count > 1;

    $cat_icons = [
        'retro'      => '🕹️',
        'puzzle'     => '🧩',
        'platformer' => '🎮',
        'action'     => '⚡',
    ];
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--scores">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon">🏆</span>
        <?php echo esc_html( $title ); ?>
      </div>

      <?php if ( empty( $games_data ) ) :
          // ── No scores: cycle through arcade game tiles as a teaser ──────
          $arcade_games    = [];
          $arcade_page_url = '';
          if ( $has_g_table ) {
              $arcade_games = $wpdb->get_results(
                  "SELECT title, slug, description, category, thumbnail_url, min_coins
                   FROM {$games_table}
                   WHERE active = 1
                   ORDER BY sort_order ASC",
                  ARRAY_A
              ) ?: [];
              if ( ! empty( $arcade_games ) ) {
                  $arcade_page_url = mfsd_hw_get_arcade_page_url();
              }
          }

          if ( empty( $arcade_games ) ) : ?>
        <div class="mfsd-hw-card__body">
          <p class="mfsd-hw-card__empty"><?php esc_html_e( 'No scores yet — visit the Arcade to play!', 'mfsd-home-widgets' ); ?></p>
        </div>

          <?php else :
              $ag_count    = count( $arcade_games );
              $ag_carousel = $ag_count > 1;
          ?>
        <div class="mfsd-hw-card__body<?php echo $ag_carousel ? ' mfsd-hw-carousel' : ''; ?> mfsd-hw-card__body--arcade-promo">

          <?php foreach ( $arcade_games as $agi => $ag ) :
              $ag_active   = $agi === 0 ? ' mfsd-hw-carousel__slide--active' : '';
              $ag_cat_icon = $cat_icons[ $ag['category'] ] ?? '🎮';
              $ag_cat_name = strtoupper( $ag['category'] ?? '' );
              $ag_thumb    = $ag['thumbnail_url'] ?? '';
              $ag_slug     = $ag['slug'] ?? '';
              $ag_play_url = $arcade_page_url
                  ? add_query_arg( 'game', $ag_slug, $arcade_page_url )
                  : '';
          ?>
            <div class="mfsd-hw-carousel__slide mfsd-hw-carousel__slide--arcade<?php echo $ag_active; ?>"
                 <?php if ( $ag_thumb ) : ?>style="--hw-arcade-thumb: url('<?php echo esc_url( $ag_thumb ); ?>')"<?php endif; ?>>
              <div class="mfsd-hw-card__arcade-category">
                <span><?php echo $ag_cat_icon; ?></span>
                <span><?php echo esc_html( $ag_cat_name ); ?></span>
              </div>
              <span class="mfsd-hw-card__arcade-name"><?php echo esc_html( $ag['title'] ); ?></span>
              <?php if ( ! empty( $ag['description'] ) ) : ?>
                <p class="mfsd-hw-card__arcade-desc"><?php echo esc_html( $ag['description'] ); ?></p>
              <?php endif; ?>
              <?php if ( ! empty( $ag['min_coins'] ) && (int) $ag['min_coins'] > 0 ) : ?>
                <span class="mfsd-hw-card__arcade-coins">🪙 <?php echo esc_html( number_format( (int) $ag['min_coins'] ) ); ?> coins to play</span>
              <?php endif; ?>
              <?php if ( $ag_play_url ) : ?>
                <a href="<?php echo esc_url( $ag_play_url ); ?>" class="mfsd-hw-card__arcade-play">
                  <?php esc_html_e( 'Play', 'mfsd-home-widgets' ); ?> →
                </a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <?php if ( $ag_carousel ) : ?>
            <div class="mfsd-hw-carousel__dots">
              <?php for ( $d = 0; $d < $ag_count; $d++ ) : ?>
                <button class="mfsd-hw-carousel__dot<?php echo $d === 0 ? ' mfsd-hw-carousel__dot--active' : ''; ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d', 'mfsd-home-widgets' ), $d + 1 ) ); ?>"></button>
              <?php endfor; ?>
            </div>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="<?php esc_attr_e( 'Previous', 'mfsd-home-widgets' ); ?>">‹</button>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="<?php esc_attr_e( 'Next', 'mfsd-home-widgets' ); ?>">›</button>
          <?php endif; ?>

        </div>
          <?php endif; ?>

      <?php else : ?>
        <div class="mfsd-hw-card__body<?php echo $is_carousel ? ' mfsd-hw-carousel' : ''; ?>">

          <?php foreach ( $games_data as $gi => $game ) :
              $active   = $gi === 0 ? ' mfsd-hw-carousel__slide--active' : '';
              $cat_icon = $cat_icons[ $game['category'] ] ?? '🎮';
          ?>
            <div class="mfsd-hw-carousel__slide<?php echo $active; ?>">
              <div class="mfsd-hw-card__game-header">
                <span class="mfsd-hw-card__game-icon"><?php echo $cat_icon; ?></span>
                <span class="mfsd-hw-card__game-name"><?php echo esc_html( $game['title'] ); ?></span>
              </div>
              <table class="mfsd-hw-scores-table">
                <tbody>
                  <?php foreach ( $game['rows'] as $ri => $row ) :
                      $is_you = ( (int) $row['student_id'] === $highlight_uid );
                      $medal  = match( $ri ) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => ( $ri + 1 ) };
                      $row_class = trim( ( $ri === 0 ? 'mfsd-hw-scores-table__row--first' : '' ) . ( $is_you ? ' mfsd-hw-scores-table__row--you' : '' ) );
                  ?>
                    <tr<?php echo $row_class ? ' class="' . esc_attr( $row_class ) . '"' : ''; ?>>
                      <td class="mfsd-hw-scores-table__rank"><?php echo $medal; ?></td>
                      <td>
                        <?php echo esc_html( $row['display_name'] ?? $row['initials'] ?? 'Unknown' ); ?>
                        <?php if ( $is_you ) : ?><span class="mfsd-hw-scores-table__you">YOU</span><?php endif; ?>
                      </td>
                      <td class="mfsd-hw-scores-table__score"><?php echo esc_html( number_format( (int) $row['score'] ) ); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>

          <?php if ( $latest_game_promo ) :
              $lg_thumb    = $latest_game_promo['thumbnail_url'] ?? '';
              $lg_cat_icon = $cat_icons[ $latest_game_promo['category'] ] ?? '🎮';
              $lg_cat_name = strtoupper( $latest_game_promo['category'] ?? '' );
              $lg_slug     = $latest_game_promo['slug'] ?? '';
              $lg_play_url = $arcade_page_url ? add_query_arg( 'game', $lg_slug, $arcade_page_url ) : '';
          ?>
            <div class="mfsd-hw-carousel__slide mfsd-hw-carousel__slide--arcade"
                 <?php if ( $lg_thumb ) : ?>style="--hw-arcade-thumb: url('<?php echo esc_url( $lg_thumb ); ?>')"<?php endif; ?>>
              <div class="mfsd-hw-card__arcade-category">
                <span><?php echo $lg_cat_icon; ?></span>
                <span><?php echo esc_html( $lg_cat_name ); ?></span>
              </div>
              <div class="mfsd-hw-card__arcade-new-badge"><?php esc_html_e( 'NEW', 'mfsd-home-widgets' ); ?></div>
              <span class="mfsd-hw-card__arcade-name"><?php echo esc_html( $latest_game_promo['title'] ); ?></span>
              <?php if ( ! empty( $latest_game_promo['description'] ) ) : ?>
                <p class="mfsd-hw-card__arcade-desc"><?php echo esc_html( $latest_game_promo['description'] ); ?></p>
              <?php endif; ?>
              <?php if ( ! empty( $latest_game_promo['min_coins'] ) && (int) $latest_game_promo['min_coins'] > 0 ) : ?>
                <span class="mfsd-hw-card__arcade-coins">🪙 <?php echo esc_html( number_format( (int) $latest_game_promo['min_coins'] ) ); ?> coins to play</span>
              <?php endif; ?>
              <?php if ( $lg_play_url ) : ?>
                <a href="<?php echo esc_url( $lg_play_url ); ?>" class="mfsd-hw-card__arcade-play">
                  <?php esc_html_e( 'Play', 'mfsd-home-widgets' ); ?> →
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ( $is_carousel ) : ?>
            <div class="mfsd-hw-carousel__dots">
              <?php for ( $d = 0; $d < $slide_count; $d++ ) : ?>
                <button class="mfsd-hw-carousel__dot<?php echo $d === 0 ? ' mfsd-hw-carousel__dot--active' : ''; ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d', 'mfsd-home-widgets' ), $d + 1 ) ); ?>"></button>
              <?php endfor; ?>
            </div>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="<?php esc_attr_e( 'Previous', 'mfsd-home-widgets' ); ?>">‹</button>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="<?php esc_attr_e( 'Next', 'mfsd-home-widgets' ); ?>">›</button>
          <?php endif; ?>

        </div>
      <?php endif; ?>

      <a href="<?php echo esc_url( home_url( '/leaderboards/' ) ); ?>" class="mfsd-hw-card__cta">
        <?php esc_html_e( 'Full Leaderboards', 'mfsd-home-widgets' ); ?>
      </a>
    </div>
    <?php
}


// ─── CARD: Progress & Achievements ───────────────────────────────────────────
// Student view: own latest badge, score, task.
// Parent view:  linked student's last completed task.
//
// v2.0.2: Task names now link directly to the task summary page.
//         CTA links to portal with student context for parents.

/**
 * Task slug → page URL mapping.
 * Extend this array as new weeks/tasks are added.
 */
function mfsd_hw_task_url_map(): array {
    return [
        // Week 1
        'solution_lens'          => '/my-future-self-foundation-course/week-1/the-solution-lens/',
        'word_association'       => '/my-future-self-foundation-course/week-1/word-association/',
        'junk_jobs'              => '/my-future-self-foundation-course/week-1/junk-jobs/',
        'personality_test_week_1'=> '/my-future-self-foundation-course/week-1/week-1-personality-test/',
        'super_strengths'        => '/my-future-self-foundation-course/week-1/super-strengths/',
        'rag_week_1'             => '/my-future-self-foundation-course/week-1/week-1-rag/',
        // Week 2 — add here when ready
        // Week 3 — add here when ready
    ];
}

/**
 * Human-friendly display name for a task slug.
 */
function mfsd_hw_task_display_name( string $slug ): string {
    $names = [
        'solution_lens'           => 'Solution Lens',
        'word_association'        => 'Word Association',
        'junk_jobs'               => 'Junk Jobs',
        'personality_test_week_1' => 'Who Am I (Part 1)',
        'super_strengths'         => 'Super Strengths',
        'rag_week_1'              => 'Weekly Check-in',
    ];
    return $names[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) );
}

/**
 * Task slug → badge slug mapping.
 * Keeps badge display in sync with the latest completed task.
 */
function mfsd_hw_task_badge_map(): array {
    return [
        'solution_lens'           => 'badge_solution_lens',
        'word_association'        => 'badge_word_assoc',
        'junk_jobs'               => 'badge_junk_jobs',
        'personality_test_week_1' => 'badge_who_am_i_1',
        'super_strengths'         => 'badge_super_strengths',
        'rag_week_1'              => 'badge_rag_w1',
    ];
}

/**
 * Task slug → icon emoji (matches parent portal metadata).
 */
function mfsd_hw_task_icon_map(): array {
    return [
        'solution_lens'           => '🔍',
        'word_association'        => '💭',
        'junk_jobs'               => '🗑️',
        'personality_test_week_1' => '🧠',
        'super_strengths'         => '💪',
        'rag_week_1'              => '🚦',
    ];
}

/**
 * Returns true if a task is configured as a family/multiplayer task.
 * Used by the parent "Next Up" slide to decide whether to show a join link.
 */
function mfsd_hw_is_family_task( string $slug ): bool {
    switch ( $slug ) {
        case 'solution_lens':
            // Family when player mode is 'multi' (the default)
            return get_option( 'mfsd_lens_player_mode', 'multi' ) === 'multi';
        case 'super_strengths':
            // Family when mode is 'full' (Extended) or 'short' (Family Short); snap is solo
            return get_option( 'mfsd_ss_mode', 'full' ) !== 'snap';
        default:
            return false;
    }
}

/**
 * Human-friendly display name for a badge slug.
 */
function mfsd_hw_badge_display_name( string $slug ): string {
    $names = [
        'badge_solution_lens'   => 'The Lens',
        'badge_word_assoc'      => 'Word Association',
        'badge_junk_jobs'       => 'Junk Jobs',
        'badge_who_am_i_1'     => 'Who Am I',
        'badge_super_strengths' => 'Super Strengths',
        'badge_rag_w1'          => 'Weekly Check-in',
        'chest_week_1'          => 'Week 1 Complete',
        'chest_achiever_1'      => 'Week 1 High Achiever',
    ];
    return $names[ $slug ] ?? ucwords( str_replace( [ 'badge_', '_' ], [ '', ' ' ], $slug ) );
}

/**
 * Get the badge image URL from the Quest Log plugin assets.
 * For Who Am I badges this returns the frame URL; the character overlay
 * is handled separately by mfsd_hw_card_progress() via mfsd_hw_get_character_avatar().
 */
function mfsd_hw_badge_image_url( string $slug, int $student_id = 0 ): string {

    // ── Solution Lens badge lives in its own plugin ───────────────────────────
    if ( $slug === 'badge_solution_lens' ) {
        return plugins_url( 'mfsd-solution-lens/images/badge_solution_lens.png' );
    }

    // ── Standard badges: use Quest Log badge artwork ─────────────────────────
    $images = [
        'badge_word_assoc'      => 'badge_word_assoc.png',
        'badge_junk_jobs'       => 'badge_junk_jobs.png',
        'badge_who_am_i_1'      => 'badge_who_am_i_1.png',
        'badge_super_strengths' => 'badge_super_strengths.png',
        'badge_rag_w1'          => 'badge_rag_w1.png',
    ];

    $filename = $images[ $slug ] ?? 'badge_locked.png';
    return plugins_url( 'mfsd-quest-log/assets/images/badges/' . $filename );
}

/**
 * Get the personality character avatar URL for a student.
 * Reads MBTI type from wp_mfsd_ptest_results and maps to the avatar filename.
 */
function mfsd_hw_get_character_avatar( int $student_id ): string {
    global $wpdb;

    $results_table = $wpdb->prefix . 'mfsd_ptest_results';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$results_table}'" ) !== $results_table ) {
        return '';
    }

    $result = $wpdb->get_row( $wpdb->prepare(
        "SELECT mbti_type FROM {$results_table}
         WHERE user_id = %d
           AND test_type IN ('COMBINED','MBTI')
           AND mbti_type IS NOT NULL
         ORDER BY created_at DESC LIMIT 1",
        $student_id
    ), ARRAY_A );

    if ( ! $result || empty( $result['mbti_type'] ) ) return '';

    $mbti = strtoupper( $result['mbti_type'] );

    // MBTI → avatar filename (matches Quest Log + personality test plugin)
    $avatar_files = [
        'ISTJ' => 'Logistician.png',  'ISFJ' => 'Defender.png',
        'ESTJ' => 'Executive.png',    'ESFJ' => 'Consul.png',
        'INTJ' => 'Architect.png',    'INTP' => 'Logician.png',
        'ENTJ' => 'Commander.png',    'ENTP' => 'Debater.png',
        'INFJ' => 'Advocate.png',     'INFP' => 'Mediatorv3.png',
        'ENFJ' => 'Protagonist.png',  'ENFP' => 'Campaigner.png',
        'ISTP' => 'Virtuoso.png',     'ISFP' => 'Adventurer.png',
        'ESTP' => 'Entrepreneur.png', 'ESFP' => 'Entertainer.png',
    ];

    $filename = $avatar_files[ $mbti ] ?? '';
    if ( ! $filename ) return '';

    // Try personality test plugin's Avatars folder first
    if ( is_dir( WP_PLUGIN_DIR . '/mfsd-personality-test/assets/Avatars/' ) ) {
        return plugins_url( 'mfsd-personality-test/assets/Avatars/' . $filename );
    }

    // Fallback: Quest Log's own characters folder
    return plugins_url( 'mfsd-quest-log/assets/images/characters/' . $filename );
}

function mfsd_hw_card_progress( array $c, string $role ): void {
    global $wpdb;

    $user_id = get_current_user_id();

    $is_parent  = in_array( $role, [ 'parent', 'teacher' ], true );
    $is_student = $role === 'student';

    $title = $is_parent
        ? __( 'STUDENT PERFORMANCE', 'mfsd-home-widgets' )
        : __( 'MY ACHIEVEMENTS', 'mfsd-home-widgets' );

    $show_badge = $is_parent || ! empty( $c['show_badge'] );
    $show_task  = true;
    $show_score = ! $is_parent && ! empty( $c['show_score'] );

    // ── Resolve student ID ───────────────────────────────────────────────────
    $student_id   = $user_id;
    $student_name = '';

    if ( $is_parent ) {
        $student_id = mfsd_hw_get_linked_student_id( $user_id );
        if ( $student_id ) {
            $student      = get_userdata( $student_id );
            $student_name = $student ? $student->display_name : '';
        }
    }

    // ── URL / badge maps (used throughout) ───────────────────────────────────
    $task_urls    = mfsd_hw_task_url_map();
    $task_badge_m = mfsd_hw_task_badge_map();

    // ── Completed tasks ───────────────────────────────────────────────────────
    // Students + parents: fetch all, oldest first — carousel cycles through them.
    // Other roles (teacher etc): just the most recent.
    $latest_task     = null;
    $completed_tasks = [];

    if ( $show_task && $student_id ) {
        $task_table = $wpdb->prefix . 'mfsd_task_progress';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$task_table}'" ) === $task_table ) {
            if ( $is_student || $is_parent ) {
                $completed_tasks = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$task_table}
                     WHERE student_id = %d AND status = 'completed'
                     ORDER BY completed_date ASC",
                    $student_id
                ), ARRAY_A ) ?: [];
                $latest_task = ! empty( $completed_tasks )
                    ? $completed_tasks[ array_key_last( $completed_tasks ) ]
                    : null;
            } else {
                $latest_task = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$task_table}
                     WHERE student_id = %d AND status = 'completed'
                     ORDER BY completed_date DESC LIMIT 1",
                    $student_id
                ), ARRAY_A );
            }
        }
    }

    // ── Next uncompleted task (students and parents with progress) ──────────
    $next_task = null;
    if ( ( $is_student || $is_parent ) && ! empty( $completed_tasks ) && $student_id ) {
        $task_order_table = $wpdb->prefix . 'mfsd_task_order';
        $enrol_table      = $wpdb->prefix . 'mfsd_enrolments';
        if (
            $wpdb->get_var( "SHOW TABLES LIKE '{$task_order_table}'" ) === $task_order_table &&
            $wpdb->get_var( "SHOW TABLES LIKE '{$enrol_table}'"      ) === $enrol_table
        ) {
            $course_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT course_id FROM {$enrol_table}
                 WHERE student_id = %d ORDER BY enrolled_date ASC LIMIT 1",
                $student_id
            ) );
            if ( $course_id ) {
                $completed_slugs = array_column( $completed_tasks, 'task_slug' );
                $placeholders    = implode( ',', array_fill( 0, count( $completed_slugs ), '%s' ) );
                $next_task       = $wpdb->get_row( $wpdb->prepare(
                    "SELECT task_slug, display_name
                     FROM {$task_order_table}
                     WHERE course_id = %d AND active = 1
                       AND task_slug NOT IN ({$placeholders})
                     ORDER BY sequence_order ASC LIMIT 1",
                    ...array_merge( [ $course_id ], $completed_slugs )
                ), ARRAY_A );
            }
        }
    }

    // ── Badge (latest task drives badge selection) ───────────────────────────
    $latest_badge = null;
    if ( $show_badge && $student_id ) {
        $badges_table = $wpdb->prefix . 'mfsd_badges';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$badges_table}'" ) === $badges_table ) {
            $derived_slug = $task_badge_m[ $latest_task['task_slug'] ?? '' ] ?? '';
            if ( $derived_slug ) {
                $latest_badge = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$badges_table}
                     WHERE student_id = %d AND badge_slug = %s LIMIT 1",
                    $student_id, $derived_slug
                ), ARRAY_A );
            }
            if ( ! $latest_badge ) {
                $latest_badge = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$badges_table}
                     WHERE student_id = %d ORDER BY earned_at DESC LIMIT 1",
                    $student_id
                ), ARRAY_A );
            }
        }
    }

    // ── Top arcade score ─────────────────────────────────────────────────────
    $latest_score = null;
    if ( $show_score && $student_id ) {
        $scores_table = $wpdb->prefix . 'mfsd_arcade_scores';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$scores_table}'" ) === $scores_table ) {
            $latest_score = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$scores_table}
                 WHERE student_id = %d ORDER BY score DESC LIMIT 1",
                $student_id
            ), ARRAY_A );
        }
    }

    // ── Enrolled-but-not-started state (students only) ───────────────────────
    $is_not_started     = false;
    $enrol_course_id    = 0;
    $enrol_course_name  = '';
    $first_task_name    = '';
    $first_task_slug    = '';
    $first_task_link    = '';
    $course_details_url = '';

    if ( ( $is_student || $is_parent ) && ! $latest_task && $student_id ) {
        $enrol_table = $wpdb->prefix . 'mfsd_enrolments';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$enrol_table}'" ) === $enrol_table ) {
            $enrollment = $wpdb->get_row( $wpdb->prepare(
                "SELECT e.course_id, c.course_name
                 FROM   {$enrol_table} e
                 INNER  JOIN {$wpdb->prefix}mfsd_courses c ON c.id = e.course_id AND c.active = 1
                 WHERE  e.student_id = %d ORDER BY e.enrolled_date ASC LIMIT 1",
                $student_id
            ), ARRAY_A );

            if ( $enrollment ) {
                $is_not_started    = true;
                $enrol_course_id   = (int) $enrollment['course_id'];
                $enrol_course_name = $enrollment['course_name'];

                $first_task = $wpdb->get_row( $wpdb->prepare(
                    "SELECT task_slug, display_name
                     FROM   {$wpdb->prefix}mfsd_task_order
                     WHERE  course_id = %d AND active = 1
                     ORDER  BY sequence_order ASC LIMIT 1",
                    $enrol_course_id
                ), ARRAY_A );

                if ( $first_task ) {
                    $first_task_slug = $first_task['task_slug'] ?? '';
                    $first_task_name = $first_task['display_name'];
                    $first_task_link = isset( $task_urls[ $first_task_slug ] )
                        ? home_url( $task_urls[ $first_task_slug ] )
                        : '';
                }

                $course_details_url = add_query_arg(
                    [ 'course_id' => $enrol_course_id, 'student_id' => $student_id ],
                    home_url( '/about/parent-portal-home/' )
                );
            }
        }
    }

    // ── Badge display values (parent / static view) ──────────────────────────
    $badge_slug_val = $latest_badge['badge_slug'] ?? '';
    $badge_name     = mfsd_hw_badge_display_name( $badge_slug_val );
    $is_who_am_i_b  = in_array( $badge_slug_val, [ 'badge_who_am_i_1', 'badge_who_am_i_2' ], true );
    $badge_char_url = ( $is_who_am_i_b && $student_id ) ? mfsd_hw_get_character_avatar( $student_id ) : '';
    $badge_img      = mfsd_hw_badge_image_url( $badge_slug_val, $student_id );

    // ── Task display values (parent / static view) ───────────────────────────
    $task_slug = $latest_task['task_slug'] ?? '';
    $task_link = isset( $task_urls[ $task_slug ] ) ? home_url( $task_urls[ $task_slug ] ) : '';
    $task_name = mfsd_hw_task_display_name( $task_slug );

    // ── CTA URL ──────────────────────────────────────────────────────────────
    $cta_url = esc_url(
        $is_not_started && $course_details_url
            ? $course_details_url
            : ( $is_parent
                ? add_query_arg( [ 'course_id' => 1, 'student_id' => $student_id ], home_url( '/about/parent-portal-home/' ) )
                : add_query_arg( [ 'course_id' => 1 ], home_url( '/about/parent-portal-home/' ) )
              )
    );
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--progress" data-widget="progress">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon"><?php echo $is_parent ? '👁' : '⭐'; ?></span>
        <?php echo esc_html( $title ); ?>
      </div>

      <?php if ( $is_student && ! empty( $completed_tasks ) ) :
          // ── STUDENT WITH PROGRESS: achievements carousel ─────────────────
          $slide_count          = count( $completed_tasks ) + ( $next_task ? 1 : 0 );
          $is_carousel          = $slide_count > 1;
          $last_completed_idx   = count( $completed_tasks ) - 1;
      ?>

        <div class="mfsd-hw-card__body<?php echo $is_carousel ? ' mfsd-hw-carousel' : ''; ?>">

          <?php foreach ( $completed_tasks as $ci => $ct ) :
              $ct_slug      = $ct['task_slug'] ?? '';
              $ct_name      = mfsd_hw_task_display_name( $ct_slug );
              // Always link: task summary page if mapped, portal progress page as fallback.
              $ct_link      = isset( $task_urls[ $ct_slug ] )
                  ? home_url( $task_urls[ $ct_slug ] )
                  : add_query_arg( [ 'course_id' => 1 ], home_url( '/about/parent-portal-home/' ) );
              $ct_date      = ! empty( $ct['completed_date'] ) ? date_i18n( 'j M Y', strtotime( $ct['completed_date'] ) ) : '';
              $ct_bslug     = $task_badge_m[ $ct_slug ] ?? '';
              $ct_bimg      = $ct_bslug ? mfsd_hw_badge_image_url( $ct_bslug, $student_id ) : '';
              $ct_who_am_i  = in_array( $ct_bslug, [ 'badge_who_am_i_1', 'badge_who_am_i_2' ], true );
              $ct_char      = ( $ct_who_am_i && $student_id ) ? mfsd_hw_get_character_avatar( $student_id ) : '';
              $ct_active    = $ci === 0 ? ' mfsd-hw-carousel__slide--active' : '';
              $ct_status    = ( $ci === $last_completed_idx ) ? '★ Last Completed' : '✓ Completed';
          ?>
            <div class="mfsd-hw-carousel__slide<?php echo $ct_active; ?>"
                 <?php if ( $ct_bimg && ! $ct_who_am_i ) : ?>style="--hw-badge-bg: url('<?php echo esc_url( $ct_bimg ); ?>')"<?php endif; ?>>
              <?php if ( $ct_who_am_i && $ct_bimg ) : ?>
                <div class="mfsd-hw-card__achievement-badge-bg-whoami"
                     style="background-image:url('<?php echo esc_url( $ct_bimg ); ?>');">
                  <?php if ( $ct_char ) : ?>
                    <img src="<?php echo esc_url( $ct_char ); ?>" alt="">
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div class="mfsd-hw-card__achievement-header">
                <span class="mfsd-hw-card__achievement-status"><?php echo esc_html( $ct_status ); ?></span>
                <?php if ( $ct_date ) : ?>
                  <span class="mfsd-hw-card__date"><?php echo esc_html( $ct_date ); ?></span>
                <?php endif; ?>
              </div>
              <a href="<?php echo esc_url( $ct_link ); ?>" class="mfsd-hw-card__task-link">
                <?php echo esc_html( $ct_name ); ?> →
              </a>
            </div>
          <?php endforeach; ?>

          <?php if ( $next_task ) :
              $task_icons = mfsd_hw_task_icon_map();
              $nt_slug    = $next_task['task_slug'] ?? '';
              $nt_name    = ! empty( $next_task['display_name'] ) ? $next_task['display_name'] : mfsd_hw_task_display_name( $nt_slug );
              $nt_link    = isset( $task_urls[ $nt_slug ] ) ? home_url( $task_urls[ $nt_slug ] ) : '';
              $nt_icon    = $task_icons[ $nt_slug ] ?? '🎯';
          ?>
            <div class="mfsd-hw-carousel__slide">
              <span class="mfsd-hw-card__task-icon-backdrop" aria-hidden="true"><?php echo $nt_icon; ?></span>
              <div class="mfsd-hw-card__achievement-header">
                <span class="mfsd-hw-card__next-label">Next Up</span>
              </div>
              <a href="<?php echo esc_url( $nt_link ?: add_query_arg( [ 'course_id' => 1 ], home_url( '/about/parent-portal-home/' ) ) ); ?>"
                 class="mfsd-hw-card__task-link mfsd-hw-card__task-link--next">
                <?php echo esc_html( $nt_name ); ?> →
              </a>
            </div>
          <?php endif; ?>

          <?php if ( $is_carousel ) : ?>
            <div class="mfsd-hw-carousel__dots">
              <?php for ( $d = 0; $d < $slide_count; $d++ ) : ?>
                <button class="mfsd-hw-carousel__dot<?php echo $d === 0 ? ' mfsd-hw-carousel__dot--active' : ''; ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d', 'mfsd-home-widgets' ), $d + 1 ) ); ?>"></button>
              <?php endfor; ?>
            </div>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="<?php esc_attr_e( 'Previous', 'mfsd-home-widgets' ); ?>">‹</button>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="<?php esc_attr_e( 'Next', 'mfsd-home-widgets' ); ?>">›</button>
          <?php endif; ?>

        </div>

      <?php elseif ( $is_parent && ! empty( $completed_tasks ) ) :
          // ── PARENT VIEW: completed tasks + optional Next Up slide ─────────
          $slide_count        = count( $completed_tasks ) + ( $next_task ? 1 : 0 );
          $is_carousel        = $slide_count > 1;
          $last_completed_idx = count( $completed_tasks ) - 1;
      ?>

        <?php if ( $student_name ) : ?>
          <p class="mfsd-hw-card__subtitle">
            <?php printf(
                esc_html__( "Showing %s's progress", 'mfsd-home-widgets' ),
                '<strong>' . esc_html( $student_name ) . '</strong>'
            ); ?>
          </p>
        <?php endif; ?>

        <div class="mfsd-hw-card__body<?php echo $is_carousel ? ' mfsd-hw-carousel' : ''; ?>">

          <?php foreach ( $completed_tasks as $ci => $ct ) :
              $ct_slug   = $ct['task_slug'] ?? '';
              $ct_name   = mfsd_hw_task_display_name( $ct_slug );
              $ct_link   = isset( $task_urls[ $ct_slug ] )
                  ? add_query_arg( 'student_id', $student_id, home_url( $task_urls[ $ct_slug ] ) )
                  : add_query_arg( [ 'course_id' => 1, 'student_id' => $student_id ], home_url( '/about/parent-portal-home/' ) );
              $ct_date   = ! empty( $ct['completed_date'] ) ? date_i18n( 'j M Y', strtotime( $ct['completed_date'] ) ) : '';
              $ct_bslug  = $task_badge_m[ $ct_slug ] ?? '';
              $ct_bimg   = $ct_bslug ? mfsd_hw_badge_image_url( $ct_bslug, $student_id ) : '';
              $ct_active = $ci === 0 ? ' mfsd-hw-carousel__slide--active' : '';
              $ct_status = ( $ci === $last_completed_idx ) ? '★ Last Completed' : '✓ Completed';
          ?>
            <div class="mfsd-hw-carousel__slide<?php echo $ct_active; ?>"
                 <?php if ( $ct_bimg ) : ?>style="--hw-badge-bg: url('<?php echo esc_url( $ct_bimg ); ?>')"<?php endif; ?>>
              <div class="mfsd-hw-card__achievement-header">
                <span class="mfsd-hw-card__achievement-status"><?php echo esc_html( $ct_status ); ?></span>
                <?php if ( $ct_date ) : ?>
                  <span class="mfsd-hw-card__date"><?php echo esc_html( $ct_date ); ?></span>
                <?php endif; ?>
              </div>
              <a href="<?php echo esc_url( $ct_link ); ?>" class="mfsd-hw-card__task-link">
                <?php echo esc_html( $ct_name ); ?> →
              </a>
            </div>
          <?php endforeach; ?>

          <?php if ( $next_task ) :
              $task_icons    = mfsd_hw_task_icon_map();
              $nt_slug       = $next_task['task_slug'] ?? '';
              $nt_name       = ! empty( $next_task['display_name'] ) ? $next_task['display_name'] : mfsd_hw_task_display_name( $nt_slug );
              $nt_icon       = $task_icons[ $nt_slug ] ?? '🎯';
              $nt_is_family  = mfsd_hw_is_family_task( $nt_slug );
              // Only link if it's a family/multiplayer task the parent can join
              $nt_link       = ( $nt_is_family && isset( $task_urls[ $nt_slug ] ) )
                  ? add_query_arg( 'student_id', $student_id, home_url( $task_urls[ $nt_slug ] ) )
                  : '';
          ?>
            <div class="mfsd-hw-carousel__slide">
              <span class="mfsd-hw-card__task-icon-backdrop" aria-hidden="true"><?php echo $nt_icon; ?></span>
              <div class="mfsd-hw-card__achievement-header">
                <span class="mfsd-hw-card__next-label">
                  <?php echo $nt_is_family
                      ? esc_html__( 'Next Up — Family Task', 'mfsd-home-widgets' )
                      : esc_html__( 'Next Up', 'mfsd-home-widgets' ); ?>
                </span>
              </div>
              <?php if ( $nt_link ) : ?>
                <a href="<?php echo esc_url( $nt_link ); ?>"
                   class="mfsd-hw-card__task-link mfsd-hw-card__task-link--next">
                  <?php echo esc_html( $nt_name ); ?> →
                </a>
              <?php else : ?>
                <span class="mfsd-hw-card__task-name">
                  <?php echo esc_html( $nt_name ); ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ( $is_carousel ) : ?>
            <div class="mfsd-hw-carousel__dots">
              <?php for ( $d = 0; $d < $slide_count; $d++ ) : ?>
                <button class="mfsd-hw-carousel__dot<?php echo $d === 0 ? ' mfsd-hw-carousel__dot--active' : ''; ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d', 'mfsd-home-widgets' ), $d + 1 ) ); ?>"></button>
              <?php endfor; ?>
            </div>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="<?php esc_attr_e( 'Previous', 'mfsd-home-widgets' ); ?>">‹</button>
            <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="<?php esc_attr_e( 'Next', 'mfsd-home-widgets' ); ?>">›</button>
          <?php endif; ?>

        </div>

      <?php elseif ( $is_parent && $is_not_started ) :
          // ── PARENT: linked student enrolled but not started ───────────────
          $ns_icons = mfsd_hw_task_icon_map();
          $ns_icon  = $ns_icons[ $first_task_slug ] ?? '🎯';
      ?>

        <?php if ( $student_name ) : ?>
          <p class="mfsd-hw-card__subtitle">
            <?php printf(
                esc_html__( "Showing %s's progress", 'mfsd-home-widgets' ),
                '<strong>' . esc_html( $student_name ) . '</strong>'
            ); ?>
          </p>
        <?php endif; ?>

        <div class="mfsd-hw-card__body mfsd-hw-carousel">
          <div class="mfsd-hw-carousel__slide mfsd-hw-carousel__slide--active mfsd-hw-carousel__slide--not-started">
            <span class="mfsd-hw-card__task-icon-backdrop" aria-hidden="true"><?php echo $ns_icon; ?></span>
            <div class="mfsd-hw-card__achievement-header">
              <span class="mfsd-hw-card__next-label"><?php esc_html_e( 'Course Not Started', 'mfsd-home-widgets' ); ?></span>
            </div>
            <?php if ( $course_details_url ) : ?>
              <a href="<?php echo esc_url( $course_details_url ); ?>"
                 class="mfsd-hw-card__task-link mfsd-hw-card__task-link--next">
                <?php esc_html_e( 'View Course Details', 'mfsd-home-widgets' ); ?> →
              </a>
            <?php endif; ?>
          </div>
        </div>

      <?php elseif ( $is_student && $is_not_started && $first_task_name ) :
          // ── STUDENT ENROLLED BUT NOT STARTED: first-task slide ───────────
          $ns_icons = mfsd_hw_task_icon_map();
          $ns_icon  = $ns_icons[ $first_task_slug ] ?? '🎯';
      ?>

        <div class="mfsd-hw-card__body mfsd-hw-carousel">
          <div class="mfsd-hw-carousel__slide mfsd-hw-carousel__slide--active mfsd-hw-carousel__slide--not-started">
            <span class="mfsd-hw-card__task-icon-backdrop" aria-hidden="true"><?php echo $ns_icon; ?></span>
            <div class="mfsd-hw-card__achievement-header">
              <span class="mfsd-hw-card__next-label"><?php esc_html_e( 'Start Your Course', 'mfsd-home-widgets' ); ?></span>
            </div>
            <a href="<?php echo esc_url( $first_task_link ?: $cta_url ); ?>"
               class="mfsd-hw-card__task-link mfsd-hw-card__task-link--next">
              <?php echo esc_html( $first_task_name ); ?> →
            </a>
          </div>
        </div>

      <?php else : ?>
        <?php // ── PARENT / TEACHER / STUDENT WITH NO PROGRESS: static view ── ?>

        <div class="mfsd-hw-card__body">

          <?php if ( $is_parent && $student_name ) : ?>
            <p class="mfsd-hw-card__subtitle">
              <?php printf(
                  esc_html__( "Showing %s's progress", 'mfsd-home-widgets' ),
                  '<strong>' . esc_html( $student_name ) . '</strong>'
              ); ?>
            </p>
          <?php elseif ( $is_parent && ! $student_id ) : ?>
            <p class="mfsd-hw-card__empty">
              <?php esc_html_e( 'No student linked to your account yet.', 'mfsd-home-widgets' ); ?>
            </p>
          <?php endif; ?>

          <?php if ( $student_id && ( $latest_badge || $latest_task ) && ( ! $is_student || $latest_task ) ) : ?>
            <div class="mfsd-hw-card__stat mfsd-hw-card__stat--badge">
              <?php if ( $show_badge && $latest_badge ) : ?>
                <?php if ( $is_who_am_i_b ) : ?>
                  <div class="mfsd-hw-card__badge-who-am-i"
                       style="width:56px;height:56px;flex-shrink:0;position:relative;background:url('<?php echo esc_url( $badge_img ); ?>') center/contain no-repeat;">
                    <?php if ( $badge_char_url ) : ?>
                      <img src="<?php echo esc_url( $badge_char_url ); ?>"
                           alt="<?php echo esc_attr( $badge_name ); ?>"
                           width="36" height="36"
                           style="width:36px;height:36px;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);object-fit:contain;">
                    <?php endif; ?>
                  </div>
                <?php else : ?>
                  <img class="mfsd-hw-card__badge-img"
                       src="<?php echo esc_url( $badge_img ); ?>"
                       alt="<?php echo esc_attr( $badge_name ); ?>">
                <?php endif; ?>
              <?php endif; ?>
              <div>
                <strong><?php echo $is_parent
                    ? esc_html__( 'Last Completed Task', 'mfsd-home-widgets' )
                    : esc_html__( 'Latest Completed Task', 'mfsd-home-widgets' );
                ?></strong><br>
                <?php if ( $show_task && $latest_task ) : ?>
                  <?php if ( $task_link ) : ?>
                    <a href="<?php echo esc_url( $task_link ); ?>" class="mfsd-hw-card__task-link">
                      <?php echo esc_html( $task_name ); ?> →
                    </a>
                  <?php else : ?>
                    <?php echo esc_html( $task_name ); ?>
                  <?php endif; ?>
                  <?php if ( ! empty( $latest_task['completed_date'] ) ) : ?>
                    <span class="mfsd-hw-card__date">
                      <?php echo esc_html( date_i18n( 'j M Y', strtotime( $latest_task['completed_date'] ) ) ); ?>
                    </span>
                  <?php endif; ?>
                <?php else : ?>
                  <?php echo esc_html( $badge_name ); ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ( $show_score && $student_id && $latest_score ) : ?>
            <div class="mfsd-hw-card__stat">
              <span class="mfsd-hw-card__stat-icon">🎮</span>
              <div>
                <strong><?php esc_html_e( 'Top Score', 'mfsd-home-widgets' ); ?></strong><br>
                <?php echo esc_html( number_format( (int) $latest_score['score'] ) ); ?>
                <?php if ( ! empty( $latest_score['game_slug'] ) ) : ?>
                  — <?php echo esc_html( ucwords( str_replace( '_', ' ', $latest_score['game_slug'] ) ) ); ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ( $student_id && ! $latest_badge && ! $latest_task && ! $latest_score ) : ?>
            <p class="mfsd-hw-card__empty">
              <?php if ( $is_parent ) : ?>
                <?php esc_html_e( 'No activity yet for your linked student.', 'mfsd-home-widgets' ); ?>
              <?php elseif ( $is_not_started && $first_task_name ) : ?>
                <?php printf(
                    __( 'Complete %1$s%2$s%3$s to start %4$s', 'mfsd-home-widgets' ),
                    $first_task_link
                        ? '<a href="' . esc_url( $first_task_link ) . '" class="mfsd-hw-card__task-link">'
                        : '<strong>',
                    esc_html( $first_task_name ),
                    $first_task_link ? '</a>' : '</strong>',
                    '<strong>' . esc_html( $enrol_course_name ) . '</strong>'
                ); ?>
              <?php else : ?>
                <?php esc_html_e( 'Complete your first activity to see achievements here!', 'mfsd-home-widgets' ); ?>
              <?php endif; ?>
            </p>
          <?php endif; ?>

        </div>

      <?php endif; // end student vs static view ?>

      <a href="<?php echo $cta_url; ?>" class="mfsd-hw-card__cta">
        <?php if ( $is_not_started && $is_student ) :
            esc_html_e( 'Start My Course', 'mfsd-home-widgets' );
        elseif ( $is_parent ) :
            esc_html_e( 'View Full Progress', 'mfsd-home-widgets' );
        else :
            esc_html_e( 'View My Progress', 'mfsd-home-widgets' );
        endif; ?>
      </a>
    </div>
    <?php
}



// ─── RSS FEED FETCH ───────────────────────────────────────────────────────────

/**
 * Fetch and cache RSS headlines for the widget.
 * Reuses the same transient approach as the ticker tape plugin if available,
 * otherwise implements its own identical logic.
 *
 * @param string $feed_url
 * @param int    $limit
 * @param string $prefix
 * @return array[]  Each item: [ 'title' => string, 'link' => string, 'summary' => string ]
 */
function mfsd_hw_fetch_rss( string $feed_url, int $limit = 10, string $prefix = '' ): array {
    if ( empty( $feed_url ) ) return [];

    $limit         = max( 1, min( 20, $limit ) );
    $transient_key = 'mfsd_hw_rss_v4_' . md5( $feed_url . $limit );

    $cached = get_transient( $transient_key );
    if ( is_array( $cached ) && ! empty( $cached ) ) return $cached;


    // Sanitise the URL — remove any backslash escaping from JSON storage.
    $feed_url = str_replace( '\/', '/', $feed_url );

    $response = wp_remote_get( $feed_url, [
        'timeout'     => 15,
        'redirection' => 5,
        'user-agent'  => 'Mozilla/5.0 (compatible; MFSDWidgets/' . MFSD_HW_VERSION . '; +https://mfsd.me)',
        'headers'     => [ 'Accept' => 'application/rss+xml, application/xml, text/xml, */*' ],
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'MFSD_HW RSS: WP_Error fetching ' . $feed_url . ' — ' . $response->get_error_message() );
        set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
        return [];
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    if ( $http_code !== 200 ) {
        error_log( 'MFSD_HW RSS: HTTP ' . $http_code . ' from ' . $feed_url );
        set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
        return [];
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        error_log( 'MFSD_HW RSS: empty body from ' . $feed_url );
        set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
        return [];
    }


    libxml_use_internal_errors( true );
    $xml = simplexml_load_string( $body );
    $xml_errors = libxml_get_errors();
    libxml_clear_errors();

    if ( $xml === false ) {
        if ( ! empty( $xml_errors ) ) {
        }
        set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
        return [];
    }


    // Support RSS 2.0 and Atom.
    $xml_items = [];
    if ( isset( $xml->channel->item ) ) {
        $xml_items = $xml->channel->item;
    } elseif ( isset( $xml->entry ) ) {
        $xml_items = $xml->entry;
    }

    $prefix  = trim( $prefix );
    $results = [];
    $count   = 0;

    // Register media namespace for feeds that use media:thumbnail / media:content.
    $namespaces = $xml->getNamespaces( true );

    foreach ( $xml_items as $item ) {
        if ( $count >= $limit ) break;
        $title   = wp_strip_all_tags( (string) $item->title );
        $link    = (string) ( $item->link ?? '' );

        // Atom feeds use link[href] attribute.
        if ( empty( $link ) && isset( $item->link ) ) {
            $link_attrs = $item->link->attributes();
            $link = (string) ( $link_attrs['href'] ?? '' );
        }

        $summary = wp_strip_all_tags( (string) ( $item->description ?? $item->summary ?? '' ) );
        if ( mb_strlen( $summary ) > 160 ) {
            $summary = mb_substr( $summary, 0, 157 ) . '…';
        }

        // ── Extract image URL ─────────────────────────────────────────────────
        $image_url = '';

        // 1. <enclosure type="image/..."> — used by Sky Sports, many news feeds.
        // SimpleXML reads enclosure attributes directly from the element.
        if ( isset( $item->enclosure ) ) {
            $enc_attrs = $item->enclosure->attributes();
            $enc_url   = (string) ( $enc_attrs['url']  ?? '' );
            $enc_type  = (string) ( $enc_attrs['type'] ?? '' );
            if ( ! empty( $enc_url ) && ( empty( $enc_type ) || strpos( $enc_type, 'image' ) !== false ) ) {
                $image_url = $enc_url;
            }
        }
        // Fallback: check enclosure as a child element via xpath.
        if ( empty( $image_url ) ) {
            $enclosures = $item->xpath( 'enclosure' );
            if ( ! empty( $enclosures ) ) {
                foreach ( $enclosures as $enc ) {
                    $enc_url  = (string) ( $enc['url']  ?? '' );
                    $enc_type = (string) ( $enc['type'] ?? '' );
                    if ( ! empty( $enc_url ) && ( empty( $enc_type ) || strpos( $enc_type, 'image' ) !== false ) ) {
                        $image_url = $enc_url;
                        break;
                    }
                }
            }
        }

        // 2. <media:thumbnail> or <media:content> — used by BBC and others.
        // Prefer media:thumbnail: it is reliably a landscape photo crop.
        // media:content can be portrait or video — worse for cover backgrounds.
        if ( empty( $image_url ) && isset( $namespaces['media'] ) ) {
            $media = $item->children( $namespaces['media'] );
            if ( isset( $media->thumbnail ) ) {
                $mt = $media->thumbnail->attributes();
                $image_url = (string) ( $mt['url'] ?? '' );
            }
            if ( empty( $image_url ) && isset( $media->content ) ) {
                $mc = $media->content->attributes();
                $mc_type = (string) ( $mc['type'] ?? '' );
                if ( strpos( $mc_type, 'image' ) !== false ) {
                    $image_url = (string) ( $mc['url'] ?? '' );
                }
            }
        }

        // 3. <image> channel-level fallback (not per-item, skip for now).

        // 4. <img> embedded in <description> HTML — used by IGN, Kotaku,
        //    and most WordPress-based gaming/news feeds. The HTML is entity-
        //    encoded in the RSS, so decode it first.
        if ( empty( $image_url ) ) {
            $raw_desc = html_entity_decode( (string) ( $item->description ?? '' ), ENT_QUOTES | ENT_HTML5 );
            if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $raw_desc, $m ) ) {
                $candidate = $m[1];
                if ( strpos( $candidate, 'http' ) === 0 ) {
                    $image_url = $candidate;
                }
            }
        }

        // 5. <content:encoded> — WordPress.com and self-hosted WP feeds include
        //    full post HTML here; grab the first <img> if description had none.
        if ( empty( $image_url ) ) {
            $item_ns = $item->getNamespaces( true );
            if ( isset( $item_ns['content'] ) ) {
                $content_ch = $item->children( $item_ns['content'] );
                if ( isset( $content_ch->encoded ) ) {
                    $raw_content = html_entity_decode( (string) $content_ch->encoded, ENT_QUOTES | ENT_HTML5 );
                    if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $raw_content, $m ) ) {
                        $candidate = $m[1];
                        if ( strpos( $candidate, 'http' ) === 0 ) {
                            $image_url = $candidate;
                        }
                    }
                }
            }
        }

        if ( $title ) {
            $results[] = [
                'title'     => $prefix !== '' ? $prefix . ' ' . $title : $title,
                'link'      => $link,
                'summary'   => $summary,
                'image_url' => esc_url( $image_url ),
            ];
            $count++;
        }
    }

    set_transient( $transient_key, $results, 30 * MINUTE_IN_SECONDS );
    return $results;
}


// ─── CARD: RSS Feed ───────────────────────────────────────────────────────────
//
// Displays live RSS headlines as a full-bleed hero carousel,
// styled consistently with the news card.
// Each headline is one carousel slide.
// Auto-rotates every 5 seconds; left/right arrows and dots for manual nav.

function mfsd_hw_card_rss( array $c ): void {
    $feed_url    = $c['feed_url']    ?? '';
    $feed_limit  = (int) ( $c['feed_limit']  ?? 10 );
    $feed_prefix = $c['feed_prefix'] ?? '';
    $badge_label = strtoupper( $c['badge_label'] ?? 'RSS NEWS' );
    $cta_text    = $c['cta_text']    ?? 'Read Full Story';
    $link_out    = ! empty( $c['link_out'] );

    $items = mfsd_hw_fetch_rss( $feed_url, $feed_limit, $feed_prefix );

    if ( empty( $items ) ) {
        ?>
        <div class="mfsd-hw-card mfsd-hw-card--news-hero mfsd-hw-card--rss">
          <div class="mfsd-hw-card__header">
            <span class="mfsd-hw-card__icon">📡</span>
            <?php echo esc_html( $badge_label ); ?>
          </div>
          <div class="mfsd-hw-card__news-body">
            <div class="mfsd-hw-carousel__slide mfsd-hw-carousel__slide--active">
              <div class="mfsd-hw-card__hero-panel mfsd-hw-card__hero-panel--empty"></div>
              <div class="mfsd-hw-card__hero-content">
                <h3 class="mfsd-hw-card__hero-headline">
                  <?php esc_html_e( 'Feed unavailable — check back soon.', 'mfsd-home-widgets' ); ?>
                </h3>
              </div>
            </div>
          </div>
        </div>
        <?php
        return;
    }

    $count       = count( $items );
    $is_carousel = $count > 1;
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--news-hero mfsd-hw-card--rss">

      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon">📡</span>
        <?php echo esc_html( $badge_label ); ?>
      </div>

      <div class="mfsd-hw-card__news-body<?php echo $is_carousel ? ' mfsd-hw-carousel' : ''; ?>">

        <?php foreach ( $items as $i => $item ) :
            $active    = $i === 0 ? ' mfsd-hw-carousel__slide--active' : '';
            $img_url   = ! empty( $item['image_url'] ) ? $item['image_url'] : '';
            $has_image = ! empty( $img_url );
        ?>
          <div class="mfsd-hw-carousel__slide<?php echo $active; ?>">
            <div class="mfsd-hw-card__hero-panel<?php echo $has_image ? '' : ' mfsd-hw-card__hero-panel--empty'; ?>">
              <?php if ( $has_image ) : ?>
                <img class="mfsd-hw-card__hero-img"
                     src="<?php echo esc_url( $img_url ); ?>"
                     alt="<?php echo esc_attr( $item['title'] ); ?>">
              <?php endif; ?>
            </div>
            <div class="mfsd-hw-card__hero-content">
              <h3 class="mfsd-hw-card__hero-headline">
                <?php echo esc_html( $item['title'] ); ?>
              </h3>
              <?php if ( ! empty( $item['summary'] ) ) : ?>
                <p class="mfsd-hw-card__hero-summary">
                  <?php echo esc_html( $item['summary'] ); ?>
                </p>
              <?php endif; ?>
              <?php if ( ! empty( $item['link'] ) ) : ?>
                <a href="<?php echo esc_url( $item['link'] ); ?>"
                   class="mfsd-hw-card__cta"
                   <?php echo $link_out ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                  <?php echo esc_html( $cta_text ); ?> →
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if ( $is_carousel ) : ?>
          <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="Previous">‹</button>
          <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="Next">›</button>
        <?php endif; ?>

      </div>

    </div>
    <?php
}


// ─── FRONTEND ASSETS ─────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'mfsd_hw_frontend_assets' );
function mfsd_hw_frontend_assets(): void {
    if ( ! is_user_logged_in() || ! is_front_page() ) return;

    wp_enqueue_style(
        'mfsd-hw-frontend',
        MFSD_HW_URI . 'assets/css/frontend.css',
        [ 'mfsd-base' ],
        MFSD_HW_VERSION
    );

    wp_enqueue_script(
        'mfsd-hw-carousel',
        MFSD_HW_URI . 'assets/js/carousel.js',
        [],
        MFSD_HW_VERSION,
        true
    );
}