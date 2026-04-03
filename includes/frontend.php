<?php
/**
 * MFSD Home Widgets — Frontend Rendering
 *
 * Renders all active widget instances visible to the current role,
 * in sort_order sequence, in a 3-column CSS grid.
 *
 * Version: 2.0.2 — News cards now use full-bleed background image style.
 */

defined( 'ABSPATH' ) || exit;


// ─── GRID RENDERER ────────────────────────────────────────────────────────────

add_action( 'mfsd_home_widgets', 'mfsd_hw_render_grid' );

function mfsd_hw_render_grid(): void {
    if ( ! is_user_logged_in() ) return;

    $role    = function_exists( 'mfsd_get_user_role' ) ? mfsd_get_user_role() : mfsd_hw_role_fallback();
    $widgets = mfsd_hw_get_for_role( $role );

    if ( empty( $widgets ) ) {
        echo '<p class="mfsd-hw-empty">' . esc_html__( 'No widgets configured.', 'mfsd-home-widgets' ) . '</p>';
        return;
    }

    echo '<div class="mfsd-hw-grid">';
    foreach ( $widgets as $w ) {
        echo '<div class="mfsd-hw-grid__cell">';
        mfsd_hw_render_widget( $w['type'], (array) $w['config'], $role );
        echo '</div>';
    }
    echo '</div>';
}

function mfsd_hw_role_fallback(): string {
    $user  = wp_get_current_user();
    $roles = (array) $user->roles;
    if ( in_array( 'administrator', $roles, true ) ) return 'admin';
    if ( in_array( 'teacher',       $roles, true ) ) return 'teacher';
    if ( in_array( 'parent',        $roles, true ) ) return 'parent';
    if ( in_array( 'student',       $roles, true ) ) return 'student';
    return 'parent';
}

function mfsd_hw_render_widget( string $type, array $config, string $role ): void {
    switch ( $type ) {
        case 'news_internal': mfsd_hw_card_news( 'internal', $config ); break;
        case 'news_external': mfsd_hw_card_news( 'external', $config ); break;
        case 'shorts':        mfsd_hw_card_shorts( $config );            break;
        case 'new_courses':   mfsd_hw_card_courses( $config );           break;
        case 'top_scores':    mfsd_hw_card_scores( $config, $role );     break;
        case 'progress':      mfsd_hw_card_progress( $config, $role );   break;
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

function mfsd_hw_card_news( string $variant, array $c ): void {
    $title    = $variant === 'internal' ? 'MFS NEWS' : 'COMMUNITY ARTICLES';
    $icon     = $variant === 'internal' ? '📣' : '📰';
    $external = $variant === 'external';
    $img      = mfsd_hw_get_image_url( (int) ( $c['image_id'] ?? 0 ) );
    $link     = $c['link'] ?? '';
    $cta_text = $c['cta_text'] ?? 'Read More';
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--news-hero">

      <?php // Background image ?>
      <div class="mfsd-hw-card__hero-bg"
           style="background-image: url('<?php echo esc_url( $img ); ?>');">
      </div>

      <?php // Gradient overlay ?>
      <div class="mfsd-hw-card__hero-overlay"></div>

      <?php // Category badge — top left ?>
      <div class="mfsd-hw-card__hero-badge">
        <span class="mfsd-hw-card__icon"><?php echo $icon; ?></span>
        <?php echo esc_html( $title ); ?>
      </div>

      <?php // Text content — bottom of card ?>
      <div class="mfsd-hw-card__hero-content">
        <h3 class="mfsd-hw-card__hero-headline">
          <?php echo esc_html( $c['headline'] ?? '' ); ?>
        </h3>
        <?php if ( ! empty( $c['summary'] ) ) : ?>
          <p class="mfsd-hw-card__hero-summary">
            <?php echo esc_html( $c['summary'] ); ?>
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

    // ── Admin config flags (student view toggles) ────────────────────────────
    // Parent view always shows everything; student view respects checkboxes.
    $show_badge = $is_parent || ! empty( $c['show_badge'] );
    $show_task  = $is_parent || ! empty( $c['show_task'] );
    $show_score = $is_parent || ! empty( $c['show_score'] );

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
            <?php echo $is_parent
                ? esc_html__( 'No activity yet for your linked student.', 'mfsd-home-widgets' )
                : esc_html__( 'Complete your first activity to see achievements here!', 'mfsd-home-widgets' );
            ?>
          </p>
        <?php endif; ?>

      </div>

      <a href="<?php echo esc_url( $is_parent
            ? add_query_arg( [ 'course_id' => 1, 'student_id' => $student_id ], home_url( '/portal-home/' ) )
            : home_url( '/portal-home/' )
         ); ?>"
         class="mfsd-hw-card__cta">
        <?php echo $is_parent
            ? esc_html__( 'View Full Progress', 'mfsd-home-widgets' )
            : esc_html__( 'View My Progress', 'mfsd-home-widgets' );
        ?>
      </a>
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
}