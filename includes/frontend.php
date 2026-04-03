<?php
/**
 * MFSD Home Widgets — Frontend
 *
 * Hooks into do_action('mfsd_home_widgets') from front-page.php.
 * Renders all active widget instances visible to the current role,
 * in sort_order sequence, in a 3-column CSS grid.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'mfsd_home_widgets', 'mfsd_hw_render_grid' );

function mfsd_hw_render_grid(): void {
    if ( ! is_user_logged_in() ) return;

    $role    = function_exists( 'mfsd_get_user_role' ) ? mfsd_get_user_role() : mfsd_hw_role_fallback();
    $widgets = mfsd_hw_get_for_role( $role );

    if ( empty( $widgets ) ) {
        echo '<p class="mfsd-hw-empty">' . esc_html__( 'No widgets configured.', 'mfsd-home-widgets' ) . '</p>';
        return;
    }

    $count = count( $widgets );
    $grid_class = 'mfsd-hw-grid';
    if ( $count === 1 ) $grid_class .= ' mfsd-hw-grid--1';
    elseif ( $count === 2 ) $grid_class .= ' mfsd-hw-grid--2';
    elseif ( $count === 4 ) $grid_class .= ' mfsd-hw-grid--4';

    echo '<div class="' . esc_attr( $grid_class ) . '">';
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


// ─── CARD RENDERERS ───────────────────────────────────────────────────────────

function mfsd_hw_card_news( string $variant, array $c ): void {
    $title    = $variant === 'internal' ? 'MFS NEWS' : 'COMMUNITY ARTICLES';
    $icon     = $variant === 'internal' ? '📣' : '📰';
    $external = $variant === 'external';
    $img      = mfsd_hw_get_image_url( (int) ( $c['image_id'] ?? 0 ) );
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--news">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon"><?php echo $icon; ?></span>
        <?php echo esc_html( $title ); ?>
      </div>
      <div class="mfsd-hw-card__body">
        <div class="mfsd-hw-card__media-row">
          <img class="mfsd-hw-card__thumb" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $c['headline'] ?? '' ); ?>">
          <div class="mfsd-hw-card__text">
            <h3 class="mfsd-hw-card__headline"><?php echo esc_html( $c['headline'] ?? '' ); ?></h3>
            <p class="mfsd-hw-card__summary"><?php echo esc_html( $c['summary'] ?? '' ); ?></p>
          </div>
        </div>
      </div>
      <?php if ( ! empty( $c['link'] ) ) : ?>
        <a href="<?php echo esc_url( $c['link'] ); ?>"
           class="mfsd-hw-card__cta"
           <?php echo $external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
          <?php echo esc_html( $c['cta_text'] ?? 'Read More' ); ?>
        </a>
      <?php endif; ?>
    </div>
    <?php
}

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
          <img class="mfsd-hw-card__thumb" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $c['headline'] ?? '' ); ?>">
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

function mfsd_hw_card_scores( array $c, string $role ): void {
    global $wpdb;

    $limit      = (int) ( $c['score_count'] ?? 5 );
    $mode       = $c['mode'] ?? 'global';
    $is_student = in_array( $role, [ 'student' ], true );
    $title      = $mode === 'student' ? "MY STUDENT'S SCORES" : 'TOP SCORES';

    $scores   = [];
    $lb_table = $wpdb->prefix . 'mfsd_arcade_scores';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$lb_table}'" ) === $lb_table ) {

        $game_where = '';
        if ( ! empty( $c['games'] ) && $c['games'] !== 'all' ) {
            $game_where = $wpdb->prepare( ' AND l.game_slug = %s', $c['games'] );
        }

        if ( $mode === 'student' && ! $is_student ) {
            $links_table = $wpdb->prefix . 'mfsd_parent_student_links';
            $student_id  = $wpdb->get_var( $wpdb->prepare(
                "SELECT student_id FROM {$links_table} WHERE parent_id = %d LIMIT 1",
                get_current_user_id()
            ) );
            if ( $student_id ) {
                $scores = $wpdb->get_results( $wpdb->prepare(
                    "SELECT l.*, u.display_name FROM {$lb_table} l
                     LEFT JOIN {$wpdb->users} u ON l.student_id = u.ID
                     WHERE l.student_id = %d {$game_where}
                     ORDER BY l.score DESC LIMIT %d",
                    $student_id, $limit
                ), ARRAY_A ) ?: [];
            }
        } else {
            $scores = $wpdb->get_results( $wpdb->prepare(
                "SELECT l.*, u.display_name FROM {$lb_table} l
                 LEFT JOIN {$wpdb->users} u ON l.student_id = u.ID
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
          <p class="mfsd-hw-card__empty"><?php esc_html_e( 'No scores yet.', 'mfsd-home-widgets' ); ?></p>
        <?php else : ?>
          <table class="mfsd-hw-scores-table">
            <thead><tr>
              <th><?php esc_html_e( 'Rank', 'mfsd-home-widgets' ); ?></th>
              <th><?php esc_html_e( 'Player', 'mfsd-home-widgets' ); ?></th>
              <th><?php esc_html_e( 'Game', 'mfsd-home-widgets' ); ?></th>
              <th><?php esc_html_e( 'Score', 'mfsd-home-widgets' ); ?></th>
            </tr></thead>
            <tbody>
              <?php foreach ( $scores as $i => $row ) : ?>
                <tr class="<?php echo $i === 0 ? 'mfsd-hw-scores-table__row--first' : ''; ?>">
                  <td class="mfsd-hw-scores-table__rank">
                    <?php echo match( $i ) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => $i + 1 }; ?>
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

function mfsd_hw_card_progress( array $c, string $role ): void {
    global $wpdb;

    $user_id    = get_current_user_id();
    $is_parent  = in_array( $role, [ 'parent', 'teacher' ], true );
    $title      = $is_parent ? 'STUDENT PERFORMANCE' : 'MY ACHIEVEMENTS';
    $student_id = $user_id;
    $student_name = '';

    if ( $is_parent ) {
        $links_table = $wpdb->prefix . 'mfsd_parent_student_links';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$links_table}'" ) === $links_table ) {
            $sid = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT student_id FROM {$links_table} WHERE parent_id = %d LIMIT 1", $user_id
            ) );
            if ( $sid ) {
                $student_id   = $sid;
                $stu          = get_userdata( $sid );
                $student_name = $stu ? $stu->display_name : '';
            }
        }
    }

    // Latest badge.
    $latest_badge = null;
    $bt = $wpdb->prefix . 'mfsd_badges';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$bt}'" ) === $bt ) {
        $latest_badge = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$bt} WHERE student_id = %d ORDER BY earned_at DESC LIMIT 1", $student_id
        ), ARRAY_A );
    }

    // Latest completed task.
    $latest_task = null;
    $tt = $wpdb->prefix . 'mfsd_task_progress';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tt}'" ) === $tt ) {
        $latest_task = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tt} WHERE user_id = %d ORDER BY completed_at DESC LIMIT 1", $student_id
        ), ARRAY_A );
    }

    // Top score.
    $latest_score = null;
    $lt = $wpdb->prefix . 'mfsd_arcade_scores';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$lt}'" ) === $lt ) {
        $latest_score = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$lt} WHERE student_id = %d ORDER BY score DESC LIMIT 1", $student_id
        ), ARRAY_A );
    }
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--progress">
      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon"><?php echo $is_parent ? '👁' : '⭐'; ?></span>
        <?php echo esc_html( $title ); ?>
      </div>
      <div class="mfsd-hw-card__body">

        <?php if ( $is_parent && $student_name ) : ?>
          <p class="mfsd-hw-card__subtitle">
            <?php printf( esc_html__( "%s's progress", 'mfsd-home-widgets' ), '<strong>' . esc_html( $student_name ) . '</strong>' ); ?>
          </p>
        <?php endif; ?>

        <?php if ( ! $is_parent && ! empty( $c['show_badge'] ) && $latest_badge ) : ?>
          <div class="mfsd-hw-progress-row">
            <span class="mfsd-hw-progress-row__icon">🏅</span>
            <div>
              <div class="mfsd-hw-progress-row__label"><?php esc_html_e( 'Latest badge', 'mfsd-home-widgets' ); ?></div>
              <div class="mfsd-hw-progress-row__value"><?php echo esc_html( $latest_badge['badge_slug'] ?? '' ); ?></div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ( ! $is_parent && ! empty( $c['show_score'] ) && $latest_score ) : ?>
          <div class="mfsd-hw-progress-row">
            <span class="mfsd-hw-progress-row__icon">🎮</span>
            <div>
              <div class="mfsd-hw-progress-row__label"><?php esc_html_e( 'Top arcade score', 'mfsd-home-widgets' ); ?></div>
              <div class="mfsd-hw-progress-row__value">
                <?php echo esc_html( number_format( (int) $latest_score['score'] ) ); ?>
                <span class="mfsd-hw-progress-row__meta"><?php echo esc_html( $latest_score['game_slug'] ?? '' ); ?></span>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ( ( ! $is_parent && ! empty( $c['show_task'] ) || $is_parent ) && $latest_task ) : ?>
          <div class="mfsd-hw-progress-row">
            <span class="mfsd-hw-progress-row__icon">✅</span>
            <div>
              <div class="mfsd-hw-progress-row__label">
                <?php echo $is_parent ? esc_html__( 'Last completed task', 'mfsd-home-widgets' ) : esc_html__( 'Latest task', 'mfsd-home-widgets' ); ?>
              </div>
              <div class="mfsd-hw-progress-row__value"><?php echo esc_html( $latest_task['task_slug'] ?? '' ); ?></div>
              <?php if ( ! empty( $latest_task['completed_at'] ) ) : ?>
                <div class="mfsd-hw-progress-row__meta"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $latest_task['completed_at'] ) ) ); ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ( ! $latest_badge && ! $latest_task && ! $latest_score ) : ?>
          <p class="mfsd-hw-card__empty">
            <?php echo $is_parent
                ? esc_html__( 'No activity yet for your linked student.', 'mfsd-home-widgets' )
                : esc_html__( 'Complete your first activity to see achievements here!', 'mfsd-home-widgets' );
            ?>
          </p>
        <?php endif; ?>

      </div>
      <a href="<?php echo esc_url( $is_parent ? home_url( '/portal-home/' ) : home_url( '/badges/' ) ); ?>" class="mfsd-hw-card__cta">
        <?php echo $is_parent ? esc_html__( 'View Progress', 'mfsd-home-widgets' ) : esc_html__( 'View Quest Log', 'mfsd-home-widgets' ); ?>
      </a>
    </div>
    <?php
}


// ─── ASSETS ──────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'mfsd_hw_frontend_assets' );
function mfsd_hw_frontend_assets(): void {
    if ( ! is_user_logged_in() || ! is_front_page() ) return;
    wp_enqueue_style( 'mfsd-hw-frontend', MFSD_HW_URI . 'assets/css/frontend.css', [ 'mfsd-base' ], MFSD_HW_VERSION );
}