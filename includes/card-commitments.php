<?php
/**
 * MFSD Home Widgets — Commitment Cards widget (7.0.0)
 * Spec: techspecs/HomeWidgets_TechSpec_v7_0_0_CHANGES.md §1 (Commitments spec §12).
 *
 * The server renders only the shell (title, tabs, skeleton). The home page may be cached,
 * so no card or child data ever goes into the HTML: assets/js/commitments-widget.js loads
 * everything over the mfsd-commitments REST API after the page renders.
 *
 * What a parent may see is enforced server-side by mfsd-commitments (moderation hiding,
 * mfsd_commitment_visible_to_parent teasers). This widget never reads cards any other way.
 */

defined( 'ABSPATH' ) || exit;

/** Is the Commitments plugin providing its API and shared check-in modal? */
function mfsd_hw_commitments_available(): bool {
    return function_exists( 'mfsd_get_open_commitments' ) && function_exists( 'mfsd_commitments_enqueue_modal' );
}

/** 'student' | 'parent' | 'preview' (admin) | '' (render nothing). */
function mfsd_hw_commitments_mode( string $role ): string {
    if ( in_array( $role, [ 'admin', 'administrator' ], true ) || current_user_can( 'manage_options' ) ) return 'preview';
    if ( $role === 'student' ) return 'student';
    if ( in_array( $role, [ 'parent', 'registeredparent' ], true ) ) {
        // registeredparent only with an active link; a parent with no linked child sees nothing.
        return mfsd_hw_get_linked_student_id( get_current_user_id() ) ? 'parent' : '';
    }
    return ''; // prepurchaseparent, teacher and anything else: never
}

/** Called from mfsd_hw_frontend_assets(): enqueue only when the widget is in this role's grid. */
function mfsd_hw_commitments_assets(): void {
    if ( ! mfsd_hw_commitments_available() ) return;
    $role = function_exists( 'mfsd_get_user_role' ) ? mfsd_get_user_role() : mfsd_hw_role_fallback();
    $has  = false;
    foreach ( mfsd_hw_get_for_role( $role ) as $w ) {
        if ( $w['type'] === 'commitment_cards' ) { $has = true; break; }
    }
    if ( ! $has || mfsd_hw_commitments_mode( $role ) === '' ) return;

    mfsd_commitments_enqueue_modal(); // shared check-in / validate modal from mfsd-commitments

    wp_enqueue_style(
        'mfsd-hw-commitments',
        MFSD_HW_URI . 'assets/css/commitments-widget.css',
        [ 'mfsd-hw-frontend' ],
        MFSD_HW_VERSION . '.' . filemtime( MFSD_HW_DIR . 'assets/css/commitments-widget.css' )
    );
    wp_enqueue_script(
        'mfsd-hw-commitments',
        MFSD_HW_URI . 'assets/js/commitments-widget.js',
        [ 'mfsd-hw-carousel', 'mfsd-commitments-modal' ],
        MFSD_HW_VERSION . '.' . filemtime( MFSD_HW_DIR . 'assets/js/commitments-widget.js' ),
        true
    );
}

function mfsd_hw_card_commitments( array $c, string $role ): void {
    if ( ! mfsd_hw_commitments_available() ) return;
    $mode = mfsd_hw_commitments_mode( $role );
    if ( $mode === '' ) return;

    $is_parent = $mode === 'parent';
    if ( $is_parent ) {
        $title = (string) ( $c['title_parent'] ?? '' );
        if ( $title === '' ) $title = __( 'Their commitments', 'mfsd-home-widgets' );
    } else {
        $title = (string) ( $c['title_student'] ?? '' );
        if ( $title === '' ) $title = __( 'My commitments', 'mfsd-home-widgets' );
    }
    $show_done    = ! array_key_exists( 'show_done', $c ) || ! empty( $c['show_done'] );
    $show_teasers = ! array_key_exists( 'show_teasers', $c ) || ! empty( $c['show_teasers'] );
    ?>
    <div class="mfsd-hw-card mfsd-hw-card--commitments mfsd-hw-commit"
         id="mfsd-commitments"
         data-mode="<?php echo esc_attr( $mode ); ?>"
         data-show-done="<?php echo $show_done ? '1' : '0'; ?>"
         data-show-teasers="<?php echo $show_teasers ? '1' : '0'; ?>"
         data-home-url="<?php echo esc_url( home_url( '/' ) ); ?>">

      <div class="mfsd-hw-card__header">
        <span class="mfsd-hw-card__icon">✅</span>
        <span class="mfsd-hw-commit__title"><?php echo esc_html( $title ); ?></span>
        <span class="mfsd-hw-commit__badge" hidden></span>
      </div>

      <div class="mfsd-hw-commit__children" role="tablist" aria-label="<?php esc_attr_e( 'Children', 'mfsd-home-widgets' ); ?>" hidden></div>

      <?php if ( $show_done ) : ?>
        <div class="mfsd-hw-commit__tabs" role="tablist">
          <button type="button" class="mfsd-hw-commit__tab is-on" role="tab" aria-selected="true" data-tab="open"><?php echo $is_parent ? esc_html__( 'In progress', 'mfsd-home-widgets' ) : esc_html__( 'My cards', 'mfsd-home-widgets' ); ?></button>
          <button type="button" class="mfsd-hw-commit__tab" id="mfsd-commitments-done" role="tab" aria-selected="false" data-tab="done"><?php esc_html_e( 'Done', 'mfsd-home-widgets' ); ?> <span class="mfsd-hw-commit__done-count"></span></button>
        </div>
      <?php endif; ?>

      <div class="mfsd-hw-commit__celebrate" hidden></div>

      <div class="mfsd-hw-commit__body" aria-live="polite">
        <div class="mfsd-hw-commit__skeleton" aria-hidden="true">
          <div class="mfsd-hw-commit__skel-line mfsd-hw-commit__skel-line--short"></div>
          <div class="mfsd-hw-commit__skel-line"></div>
          <div class="mfsd-hw-commit__skel-line"></div>
          <div class="mfsd-hw-commit__skel-btn"></div>
        </div>
      </div>
    </div>
    <?php
}
