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

    $scores   = [];
    $lb_table = $wpdb->prefix . 'mfsd_leaderboard';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$lb_table}'" ) === $lb_table ) {

        $game_where = '';
        if ( ! empty( $c['games'] ) && $c['games'] !== 'all' ) {
            $game_where = $wpdb->prepare( ' AND l.game_slug = %s', $c['games'] );
        }

        if ( $mode === 'student' && ! $is_student ) {
            // Parent view: show linked student's scores.
            // FIX: use centralised helper with correct column names.
            $student_id = mfsd_hw_get_linked_student_id( get_current_user_id() );

            if ( $student_id ) {
                $scores = $wpdb->get_results( $wpdb->prepare(
                    "SELECT l.*, u.display_name FROM {$lb_table} l
                     LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                     WHERE l.user_id = %d {$game_where}
                     ORDER BY l.score DESC LIMIT %d",
                    $student_id, $limit
                ), ARRAY_A ) ?: [];
            }
        } else {
            // Global leaderboard.
            $scores = $wpdb->get_results( $wpdb->prepare(
                "SELECT l.*, u.display_name FROM {$lb_table} l
                 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                 WHERE 1=1 {$game_where}
                 ORDER BY l.score DESC LIMIT %d",
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
// FIX (v2.0.1): Corrected column names throughout:
//   - parent_student_links: parent_user_id / student_user_id (was parent_id / student_id)
//   - task_progress: student_id (was user_id), completed_date (was completed_at)
//   - Added link_status = 'active' filter and is_primary_contact DESC ordering
//   - Now uses centralised mfsd_hw_get_linked_student_id() helper

function mfsd_hw_card_progress( array $c, string $role ): void {
    global $wpdb;

    $user_id = get_current_user_id();

    $is_parent  = in_array( $role, [ 'parent', 'teacher' ], true );
    $is_student = $role === 'student';

    $title = $is_parent
        ? __( 'STUDENT PERFORMANCE', 'mfsd-home-widgets' )
        : __( 'MY ACHIEVEMENTS', 'mfsd-home-widgets' );

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
    // Column: user_id (confirmed from Quest Log plugin schema)
    $latest_badge = null;
    if ( $student_id ) {
        $badges_table = $wpdb->prefix . 'mfsd_badges';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$badges_table}'" ) === $badges_table ) {
            $latest_badge = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$badges_table}
                 WHERE user_id = %d
                 ORDER BY earned_at DESC
                 LIMIT 1",
                $student_id
            ), ARRAY_A );
        }
    }

    // ── Latest completed task (from ordering system: wp_mfsd_task_progress) ──
    // FIX: column is student_id (not user_id), completed_date (not completed_at)
    $latest_task = null;
    if ( $student_id ) {
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

    // ── Latest arcade score (from leaderboard: wp_mfsd_leaderboard) ──────────
    // Column: user_id (confirmed from Arcade plugin schema)
    $latest_score = null;
    if ( $student_id ) {
        $lb_table = $wpdb->prefix . 'mfsd_leaderboard';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$lb_table}'" ) === $lb_table ) {
            $latest_score = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$lb_table}
                 WHERE user_id = %d
                 ORDER BY score DESC
                 LIMIT 1",
                $student_id
            ), ARRAY_A );
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

        <?php if ( $student_id && $latest_badge ) : ?>
          <div class="mfsd-hw-card__stat">
            <span class="mfsd-hw-card__stat-icon">🏅</span>
            <div>
              <strong><?php esc_html_e( 'Latest Badge', 'mfsd-home-widgets' ); ?></strong><br>
              <?php echo esc_html( ucwords( str_replace( '_', ' ', $latest_badge['badge_slug'] ?? '' ) ) ); ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ( $student_id && $latest_task ) : ?>
          <div class="mfsd-hw-card__stat">
            <span class="mfsd-hw-card__stat-icon">✅</span>
            <div>
              <strong><?php echo $is_parent
                  ? esc_html__( 'Last Completed Task', 'mfsd-home-widgets' )
                  : esc_html__( 'My Last Task', 'mfsd-home-widgets' );
              ?></strong><br>
              <?php echo esc_html( ucwords( str_replace( '_', ' ', $latest_task['task_slug'] ?? '' ) ) ); ?>
              <?php if ( ! empty( $latest_task['completed_date'] ) ) : ?>
                <span class="mfsd-hw-card__date">
                  <?php echo esc_html( date_i18n( 'j M Y', strtotime( $latest_task['completed_date'] ) ) ); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ( $student_id && $latest_score ) : ?>
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

      <a href="<?php echo esc_url( $is_parent ? home_url( '/portal-home/' ) : home_url( '/badges/' ) ); ?>"
         class="mfsd-hw-card__cta">
        <?php echo $is_parent
            ? esc_html__( 'View Progress', 'mfsd-home-widgets' )
            : esc_html__( 'View Quest Log', 'mfsd-home-widgets' );
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