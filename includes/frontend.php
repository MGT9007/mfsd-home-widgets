<?php
/**
 * MFSD Home Widgets — Frontend Rendering
 *
 * Renders all active widget instances visible to the current role,
 * in sort_order sequence, in a 3-column CSS grid.
 *
 * Version: 3.3.0 — News cards now use full-bleed background image style.
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
    // For other counts use automatic sizing classes.
    if ( $count === 7 ) {
        $mod_class = ' mfsd-hw-grid--' . ( in_array( $layout, [ '7b', '7c' ], true ) ? $layout : '7' );
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
    return in_array( $val, [ '7', '7b', '7c' ], true ) ? $val : '7';
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
        case 'rss_feed':      error_log('MFSD_HW: render_widget rss_feed called'); mfsd_hw_card_rss( $config );               break;
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
        // Old single-item config — wrap in array.
        $items = [ [
            'headline' => $c['headline'] ?? '',
            'summary'  => $c['summary']  ?? '',
            'image_id' => $c['image_id'] ?? 0,
            'link'     => $c['link']     ?? '',
            'cta_text' => $c['cta_text'] ?? 'Read More',
        ] ];
    }

    // Filter out empty items (no headline and no image).
    $items = array_values( array_filter( $items, function( $item ) {
        return ! empty( $item['headline'] ) || ! empty( $item['image_id'] );
    } ) );

    if ( empty( $items ) ) return;

    $count       = count( $items );
    $is_carousel = $count > 1;
    $wrapper_cls = 'mfsd-hw-card mfsd-hw-card--news-hero';
    if ( $is_carousel ) $wrapper_cls .= ' mfsd-hw-carousel';
    ?>
    <div class="<?php echo esc_attr( $wrapper_cls ); ?>">

      <?php // ── Slides ── ?>
      <?php foreach ( $items as $i => $item ) :
          $img      = mfsd_hw_get_image_url( (int) ( $item['image_id'] ?? 0 ) );
          $link     = $item['link'] ?? '';
          $cta_text = $item['cta_text'] ?? 'Read More';
          $active   = $i === 0 ? ' mfsd-hw-carousel__slide--active' : '';
      ?>
        <div class="mfsd-hw-carousel__slide<?php echo $active; ?>">

          <div class="mfsd-hw-card__hero-bg"
               style="background-image: url('<?php echo esc_url( $img ); ?>');">
          </div>
          <div class="mfsd-hw-card__hero-overlay"></div>

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
                 class="mfsd-hw-card__hero-cta"
                 <?php echo $external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <?php echo esc_html( $cta_text ); ?> →
              </a>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>

      <?php // ── Badge — always visible on top ── ?>
      <div class="mfsd-hw-card__hero-badge">
        <span class="mfsd-hw-card__icon"><?php echo $icon; ?></span>
        <?php echo esc_html( $title ); ?>
      </div>

      <?php // ── Carousel controls (only for 2+ items) ── ?>
      <?php if ( $is_carousel ) : ?>
        <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="Previous">‹</button>
        <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="Next">›</button>
        <div class="mfsd-hw-carousel__dots">
          <?php for ( $d = 0; $d < $count; $d++ ) : ?>
            <button class="mfsd-hw-carousel__dot<?php echo $d === 0 ? ' mfsd-hw-carousel__dot--active' : ''; ?>"
                    aria-label="Slide <?php echo $d + 1; ?>"></button>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

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
      <div class="mfsd-hw-card__body">
        <div class="mfsd-hw-card__media-row">
          <img class="mfsd-hw-card__thumb"
               src="<?php echo esc_url( $img ); ?>"
               alt="<?php echo esc_attr( $c['headline'] ?? '' ); ?>">
          <div class="mfsd-hw-card__text">
            <h3 class="mfsd-hw-card__headline"><?php echo esc_html( $c['headline'] ?? '' ); ?></h3>
            <p class="mfsd-hw-card__summary"><?php echo esc_html( $c['summary'] ?? '' ); ?></p>
          </div>
        </div>
      </div>
      <?php if ( ! empty( $c['link'] ) ) : ?>
        <a href="<?php echo esc_url( $c['link'] ); ?>" class="mfsd-hw-card__cta">
          <?php echo esc_html( $c['cta_text'] ?? 'Course Details' ); ?>
        </a>
      <?php endif; ?>
    </div>
    <?php
}


// ─── CARD: Top Scores / Leaderboard ──────────────────────────────────────────

function mfsd_hw_card_scores( array $c, string $role ): void {
    global $wpdb;

    $limit      = (int) ( $c['score_count'] ?? 5 );
    $mode       = $c['mode'] ?? 'global';
    $is_student = $role === 'student';
    $title      = $mode === 'student' ? "MY STUDENT'S SCORES" : 'TOP SCORES';

    $scores      = [];
    // FIX: actual table is wp_mfsd_arcade_scores (not wp_mfsd_leaderboard)
    // Columns: student_id, score, game_slug, initials, created_at
    $scores_table = $wpdb->prefix . 'mfsd_arcade_scores';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$scores_table}'" ) === $scores_table ) {

        $game_where = '';
        if ( ! empty( $c['games'] ) && $c['games'] !== 'all' ) {
            $game_where = $wpdb->prepare( ' AND s.game_slug = %s', $c['games'] );
        }

        if ( $mode === 'student' && ! $is_student ) {
            // Parent view: show linked student's scores.
            $student_id = mfsd_hw_get_linked_student_id( get_current_user_id() );

            if ( $student_id ) {
                $scores = $wpdb->get_results( $wpdb->prepare(
                    "SELECT s.*, u.display_name
                     FROM {$scores_table} s
                     LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
                     WHERE s.student_id = %d {$game_where}
                     ORDER BY s.score DESC LIMIT %d",
                    $student_id, $limit
                ), ARRAY_A ) ?: [];
            }
        } else {
            // Global leaderboard.
            $scores = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.*, u.display_name
                 FROM {$scores_table} s
                 LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
                 WHERE 1=1 {$game_where}
                 ORDER BY s.score DESC LIMIT %d",
                $limit
            ), ARRAY_A ) ?: [];
        }
    }
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--scores">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon">🏆</span>
        <?php echo esc_html( $title ); ?>
      </div>
      <div class="mfsd-hw-card__body">
        <?php if ( empty( $scores ) ) : ?>
          <p class="mfsd-hw-card__empty"><?php esc_html_e( 'No scores recorded yet.', 'mfsd-home-widgets' ); ?></p>
        <?php else : ?>
          <table class="mfsd-hw-scores-table">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Rank', 'mfsd-home-widgets' ); ?></th>
                <th><?php esc_html_e( 'Player', 'mfsd-home-widgets' ); ?></th>
                <th><?php esc_html_e( 'Game', 'mfsd-home-widgets' ); ?></th>
                <th><?php esc_html_e( 'Score', 'mfsd-home-widgets' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $scores as $i => $row ) : ?>
                <tr class="<?php echo $i === 0 ? 'mfsd-hw-scores-table__row--first' : ''; ?>">
                  <td class="mfsd-hw-scores-table__rank">
                    <?php echo $i === 0 ? '🥇' : ( $i === 1 ? '🥈' : ( $i === 2 ? '🥉' : ( $i + 1 ) ) ); ?>
                  </td>
                  <td><?php echo esc_html( $row['display_name'] ?? 'Unknown' ); ?></td>
                  <td><?php echo esc_html( $row['game_slug'] ?? '' ); ?></td>
                  <td class="mfsd-hw-scores-table__score"><?php echo esc_html( number_format( (int) $row['score'] ) ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
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
        'word_association'        => 'Word Association',
        'junk_jobs'               => 'Junk Jobs',
        'personality_test_week_1' => 'Who Am I (Part 1)',
        'super_strengths'         => 'Super Strengths',
        'rag_week_1'              => 'Weekly Check-in',
    ];
    return $names[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) );
}

/**
 * Human-friendly display name for a badge slug.
 */
function mfsd_hw_badge_display_name( string $slug ): string {
    $names = [
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
 * Who Am I badges use the personality character avatar instead.
 */
function mfsd_hw_badge_image_url( string $slug, int $student_id = 0 ): string {

    // ── Who Am I badges: pull the personality character avatar ────────────────
    if ( in_array( $slug, [ 'badge_who_am_i_1', 'badge_who_am_i_2' ], true ) && $student_id ) {
        $avatar_url = mfsd_hw_get_character_avatar( $student_id );
        if ( $avatar_url ) return $avatar_url;
    }

    // ── Standard badges: use Quest Log badge artwork ─────────────────────────
    $images = [
        'badge_word_assoc'      => 'badge_word_assoc.png',
        'badge_junk_jobs'       => 'badge_junk_jobs.png',
        'badge_who_am_i_1'     => 'badge_who_am_i_1.png',
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

    // ── Admin config flags ──────────────────────────────────────────────────
    // Parent view: badge + task only (no score — parent just needs the task summary).
    // Student view: respects admin checkboxes.
    $show_badge = $is_parent || ! empty( $c['show_badge'] );
    $show_task  = true; // always show the task
    $show_score = ! $is_parent && ! empty( $c['show_score'] );

    // ── Resolve the student ID ───────────────────────────────────────────────
    $student_id   = $user_id;
    $student_name = '';

    if ( $is_parent ) {
        $student_id = mfsd_hw_get_linked_student_id( $user_id );

        if ( $student_id ) {
            $student = get_userdata( $student_id );
            $student_name = $student ? $student->display_name : '';
        }
    }

    // ── Latest badge (from Quest Log: wp_mfsd_badges) ────────────────────────
    // FIX: column is student_id (not user_id)
    $latest_badge = null;
    if ( $show_badge && $student_id ) {
        $badges_table = $wpdb->prefix . 'mfsd_badges';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$badges_table}'" ) === $badges_table ) {
            $latest_badge = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$badges_table}
                 WHERE student_id = %d
                 ORDER BY earned_at DESC
                 LIMIT 1",
                $student_id
            ), ARRAY_A );
        }
    }

    // ── Latest completed task (from ordering system: wp_mfsd_task_progress) ──
    $latest_task = null;
    if ( $show_task && $student_id ) {
        $task_table = $wpdb->prefix . 'mfsd_task_progress';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$task_table}'" ) === $task_table ) {
            $latest_task = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$task_table}
                 WHERE student_id = %d
                   AND status = 'completed'
                 ORDER BY completed_date DESC
                 LIMIT 1",
                $student_id
            ), ARRAY_A );
        }
    }

    // ── Latest arcade score (from Arcade: wp_mfsd_arcade_scores) ─────────────
    $latest_score = null;
    if ( $show_score && $student_id ) {
        $scores_table = $wpdb->prefix . 'mfsd_arcade_scores';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$scores_table}'" ) === $scores_table ) {
            $latest_score = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$scores_table}
                 WHERE student_id = %d
                 ORDER BY score DESC
                 LIMIT 1",
                $student_id
            ), ARRAY_A );
        }
    }

    // ── Resolve task URL for deep linking ────────────────────────────────────
    $task_slug = $latest_task['task_slug'] ?? '';
    $task_urls = mfsd_hw_task_url_map();
    $task_link = isset( $task_urls[ $task_slug ] ) ? home_url( $task_urls[ $task_slug ] ) : '';
    $task_name = mfsd_hw_task_display_name( $task_slug );

    // ── Resolve badge display ────────────────────────────────────────────────
    $badge_slug_val = $latest_badge['badge_slug'] ?? '';
    $badge_name     = mfsd_hw_badge_display_name( $badge_slug_val );
    $badge_img      = mfsd_hw_badge_image_url( $badge_slug_val, $student_id );

    // ── Detect enrolled-but-not-started state (students only) ────────────────
    // Uses wp_mfsd_enrolments (from MFSD Ordering Utility) — no status column,
    // a row existing means the student is enrolled.
    // First task is read dynamically from wp_mfsd_task_order (sequence_order=1)
    // so it stays correct as admins re-order tasks in Course Manager.
    $is_not_started     = false;
    $enrol_course_id    = 0;
    $enrol_course_name  = '';
    $first_task_name    = '';
    $first_task_link    = '';
    $course_details_url = '';

    if ( $is_student && ! $latest_task && ! $latest_badge && ! $latest_score && $student_id ) {
        $enrol_table = $wpdb->prefix . 'mfsd_enrolments';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$enrol_table}'" ) === $enrol_table ) {
            $enrollment = $wpdb->get_row( $wpdb->prepare(
                "SELECT e.course_id, c.course_name
                 FROM   {$enrol_table} e
                 INNER  JOIN {$wpdb->prefix}mfsd_courses c ON c.id = e.course_id AND c.active = 1
                 WHERE  e.student_id = %d
                 ORDER  BY e.enrolled_date ASC
                 LIMIT  1",
                $student_id
            ), ARRAY_A );

            if ( $enrollment ) {
                $is_not_started    = true;
                $enrol_course_id   = (int) $enrollment['course_id'];
                $enrol_course_name = $enrollment['course_name'];

                // Fetch the first task in the course by sequence_order.
                $first_task = $wpdb->get_row( $wpdb->prepare(
                    "SELECT task_slug, display_name
                     FROM   {$wpdb->prefix}mfsd_task_order
                     WHERE  course_id = %d AND active = 1
                     ORDER  BY sequence_order ASC
                     LIMIT  1",
                    $enrol_course_id
                ), ARRAY_A );

                if ( $first_task ) {
                    $first_task_name = $first_task['display_name'];
                    $first_task_slug = $first_task['task_slug'];
                    $first_task_link = isset( $task_urls[ $first_task_slug ] )
                        ? home_url( $task_urls[ $first_task_slug ] )
                        : '';
                }

                $course_details_url = add_query_arg(
                    [ 'course_id' => $enrol_course_id ],
                    home_url( '/about/parent-portal-home/' )
                );
            }
        }
    }
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--progress" data-widget="progress">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon"><?php echo $is_parent ? '👁' : '⭐'; ?></span>
        <?php echo esc_html( $title ); ?>
      </div>
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

        <?php if ( $student_id && ( $latest_badge || $latest_task ) ) : ?>
          <div class="mfsd-hw-card__stat mfsd-hw-card__stat--badge">
            <?php if ( $show_badge && $latest_badge ) : ?>
              <img class="mfsd-hw-card__badge-img"
                   src="<?php echo esc_url( $badge_img ); ?>"
                   alt="<?php echo esc_attr( $badge_name ); ?>">
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
              <?php
              // "Complete Word Association to start My Future Self Foundation Course"
              printf(
                  /* translators: 1: link open tag, 2: first task name, 3: link close tag, 4: course name */
                  __( 'Complete %1$s%2$s%3$s to start %4$s', 'mfsd-home-widgets' ),
                  $first_task_link
                      ? '<a href="' . esc_url( $first_task_link ) . '" class="mfsd-hw-card__task-link">'
                      : '<strong>',
                  esc_html( $first_task_name ),
                  $first_task_link ? '</a>' : '</strong>',
                  '<strong>' . esc_html( $enrol_course_name ) . '</strong>'
              );
              ?>
            <?php else : ?>
              <?php esc_html_e( 'Complete your first activity to see achievements here!', 'mfsd-home-widgets' ); ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>

      </div>

      <a href="<?php echo esc_url(
            $is_not_started && $course_details_url
                ? $course_details_url
                : ( $is_parent
                    ? add_query_arg( [ 'course_id' => 1, 'student_id' => $student_id ], home_url( '/about/parent-portal-home/' ) )
                    : add_query_arg( [ 'course_id' => 1 ], home_url( '/about/parent-portal-home/' ) )
                  )
         ); ?>"
         class="mfsd-hw-card__cta">
        <?php if ( $is_not_started ) :
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
    $transient_key = 'mfsd_hw_rss_v3_' . md5( $feed_url . $limit );

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
        set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
        return [];
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    if ( $http_code !== 200 ) {
        set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
        return [];
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
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

        // 2. <media:content> or <media:thumbnail> — used by BBC and others.
        if ( empty( $image_url ) && isset( $namespaces['media'] ) ) {
            $media = $item->children( $namespaces['media'] );
            if ( isset( $media->thumbnail ) ) {
                $mt = $media->thumbnail->attributes();
                $image_url = (string) ( $mt['url'] ?? '' );
            }
            if ( empty( $image_url ) && isset( $media->content ) ) {
                $mc = $media->content->attributes();
                $mc_type = (string) ( $mc['type'] ?? '' );
                if ( empty( $mc_type ) || strpos( $mc_type, 'image' ) !== false ) {
                    $image_url = (string) ( $mc['url'] ?? '' );
                }
            }
        }

        // 3. <image> channel-level fallback (not per-item, skip for now).

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
        // Show a placeholder card if feed is empty or unreachable.
        ?>
        <div class="mfsd-hw-card mfsd-hw-card--news-hero">
          <div class="mfsd-hw-carousel__slide mfsd-hw-carousel__slide--active">
            <div class="mfsd-hw-card__hero-bg" style="background-image:none;background-color:#1a1a1a;"></div>
            <div class="mfsd-hw-card__hero-overlay"></div>
            <div class="mfsd-hw-card__hero-content">
              <h3 class="mfsd-hw-card__hero-headline">
                <?php esc_html_e( 'Feed unavailable — check back soon.', 'mfsd-home-widgets' ); ?>
              </h3>
            </div>
          </div>
          <div class="mfsd-hw-card__hero-badge">
            <span class="mfsd-hw-card__icon">📡</span>
            <?php echo esc_html( $badge_label ); ?>
          </div>
        </div>
        <?php
        return;
    }

    $count       = count( $items );
    $is_carousel = $count > 1;
    $wrapper_cls = 'mfsd-hw-card mfsd-hw-card--news-hero mfsd-hw-card--rss';
    if ( $is_carousel ) $wrapper_cls .= ' mfsd-hw-carousel';
    ?>
    <div class="<?php echo esc_attr( $wrapper_cls ); ?>">

      <?php foreach ( $items as $i => $item ) :
          $active    = $i === 0 ? ' mfsd-hw-carousel__slide--active' : '';
          $img_url   = ! empty( $item['image_url'] ) ? $item['image_url'] : '';
          $has_image = ! empty( $img_url );
      ?>
        <div class="mfsd-hw-carousel__slide<?php echo $active; ?>">

          <div class="mfsd-hw-card__hero-bg<?php echo $has_image ? '' : ' mfsd-hw-card__hero-bg--rss'; ?>"
               style="<?php echo $has_image ? 'background-image:url(' . esc_url( $img_url ) . ');' : 'background-image:none;'; ?>">
          </div>
          <div class="mfsd-hw-card__hero-overlay<?php echo $has_image ? '' : ' mfsd-hw-card__hero-overlay--rss'; ?>"></div>

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
                 class="mfsd-hw-card__hero-cta"
                 <?php echo $link_out ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <?php echo esc_html( $cta_text ); ?> →
              </a>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>

      <?php // Badge — always visible ?>
      <div class="mfsd-hw-card__hero-badge">
        <span class="mfsd-hw-card__icon">📡</span>
        <?php echo esc_html( $badge_label ); ?>
      </div>

      <?php if ( $is_carousel ) : ?>
        <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--prev" aria-label="Previous">‹</button>
        <button class="mfsd-hw-carousel__arrow mfsd-hw-carousel__arrow--next" aria-label="Next">›</button>
        <div class="mfsd-hw-carousel__dots">
          <?php for ( $d = 0; $d < $count; $d++ ) : ?>
            <button class="mfsd-hw-carousel__dot<?php echo $d === 0 ? ' mfsd-hw-carousel__dot--active' : ''; ?>"
                    aria-label="Slide <?php echo $d + 1; ?>"></button>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

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