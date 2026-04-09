<?php
/**
 * MFSD Home Widgets — Admin
 *
 * Single admin page with two views:
 *   LIST  — shows all widget instances in a table (like a post list)
 *   FORM  — add new or edit an existing instance
 *
 * Because widgets are instances, the admin works like:
 *   "Add Widget" → choose type → fill in content → save
 * You can add Internal News 3 times, External News twice, etc.
 */

defined( 'ABSPATH' ) || exit;


// ─── MENU ────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'mfsd_hw_register_menu' );
function mfsd_hw_register_menu(): void {
    add_menu_page(
        __( 'MFSD Home Widgets', 'mfsd-home-widgets' ),
        __( 'MFSD Widgets', 'mfsd-home-widgets' ),
        'manage_options',
        'mfsd-home-widgets',
        'mfsd_hw_render_admin_page',
        'dashicons-grid-view',
        57
    );
}


// ─── ASSETS ──────────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'mfsd_hw_admin_assets' );
function mfsd_hw_admin_assets(): void {
    wp_enqueue_media();
    wp_enqueue_style(  'mfsd-hw-admin', MFSD_HW_URI . 'assets/css/admin.css', [], MFSD_HW_VERSION );
    wp_enqueue_script( 'mfsd-hw-admin', MFSD_HW_URI . 'assets/js/admin.js', [ 'jquery' ], MFSD_HW_VERSION, true );
}


// ─── FORM HANDLERS ───────────────────────────────────────────────────────────

add_action( 'admin_post_mfsd_hw_save', 'mfsd_hw_handle_save' );
function mfsd_hw_handle_save(): void {
    check_admin_referer( 'mfsd_hw_save' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );

    $id   = (int) ( $_POST['widget_id'] ?? 0 );
    $type = sanitize_key( $_POST['type'] ?? '' );

    if ( ! array_key_exists( $type, mfsd_hw_widget_types() ) ) {
        wp_die( 'Invalid widget type' );
    }

    $roles_raw = $_POST['roles'] ?? [];
    $roles     = is_array( $roles_raw ) ? array_map( 'sanitize_key', $roles_raw ) : [ 'all' ];
    if ( empty( $roles ) ) $roles = [ 'all' ];

    $data = [
        'type'       => $type,
        'label'      => sanitize_text_field( $_POST['label'] ?? '' ),
        'roles'      => $roles,
        'active'     => isset( $_POST['active'] ) ? 1 : 0,
        'sort_order' => (int) ( $_POST['sort_order'] ?? 0 ),
        'config'     => mfsd_hw_sanitize_config( $type, $_POST['config'] ?? [] ),
    ];

    if ( $id > 0 ) {
        mfsd_hw_update( $id, $data );
    } else {
        $id = mfsd_hw_insert( $data );
    }

    wp_redirect( add_query_arg( [ 'page' => 'mfsd-home-widgets', 'msg' => 'saved' ], admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_post_mfsd_hw_delete', 'mfsd_hw_handle_delete' );
function mfsd_hw_handle_delete(): void {
    check_admin_referer( 'mfsd_hw_delete' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
    $id = (int) ( $_GET['id'] ?? 0 );
    if ( $id > 0 ) mfsd_hw_delete( $id );
    wp_redirect( add_query_arg( [ 'page' => 'mfsd-home-widgets', 'msg' => 'deleted' ], admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_post_mfsd_hw_toggle', 'mfsd_hw_handle_toggle' );
function mfsd_hw_handle_toggle(): void {
    check_admin_referer( 'mfsd_hw_toggle' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
    $id     = (int) ( $_GET['id'] ?? 0 );
    $widget = $id ? mfsd_hw_get( $id ) : null;
    if ( $widget ) {
        mfsd_hw_update( $id, array_merge( $widget, [ 'active' => $widget['active'] ? 0 : 1 ] ) );
    }
    wp_redirect( add_query_arg( [ 'page' => 'mfsd-home-widgets', 'msg' => 'toggled' ], admin_url( 'admin.php' ) ) );
    exit;
}


// ─── CONFIG SANITISER ────────────────────────────────────────────────────────

function mfsd_hw_sanitize_config( string $type, array $raw ): array {
    switch ( $type ) {
        case 'news_internal':
        case 'news_external':
            // Multi-item carousel config (up to 10 items).
            $items = [];
            if ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
                foreach ( array_slice( $raw['items'], 0, 10 ) as $item ) {
                    $headline = sanitize_text_field( $item['headline'] ?? '' );
                    $image_id = (int) ( $item['image_id'] ?? 0 );
                    // Skip completely empty items.
                    if ( $headline === '' && $image_id === 0 ) continue;
                    $items[] = [
                        'headline' => $headline,
                        'summary'  => sanitize_textarea_field( $item['summary'] ?? '' ),
                        'image_id' => $image_id,
                        'link'     => esc_url_raw( $item['link'] ?? '' ),
                        'cta_text' => sanitize_text_field( $item['cta_text'] ?? 'Read More' ),
                    ];
                }
            }
            return [ 'items' => $items ];

        case 'new_courses':
            return [
                'headline' => sanitize_text_field( $raw['headline'] ?? '' ),
                'summary'  => sanitize_textarea_field( $raw['summary'] ?? '' ),
                'image_id' => (int) ( $raw['image_id'] ?? 0 ),
                'link'     => esc_url_raw( $raw['link'] ?? '' ),
                'cta_text' => sanitize_text_field( $raw['cta_text'] ?? 'Course Details' ),
            ];

        case 'shorts':
            return [
                'title'     => sanitize_text_field( $raw['title'] ?? '' ),
                'video_url' => esc_url_raw( $raw['video_url'] ?? '' ),
                'image_id'  => (int) ( $raw['image_id'] ?? 0 ),
                'duration'  => sanitize_text_field( $raw['duration'] ?? '' ),
                'cta_text'  => sanitize_text_field( $raw['cta_text'] ?? 'Watch Now' ),
            ];

        case 'top_scores':
            return [
                'games'       => sanitize_text_field( $raw['games'] ?? 'all' ),
                'score_count' => (int) ( $raw['score_count'] ?? 5 ),
                'mode'        => sanitize_key( $raw['mode'] ?? 'global' ),
            ];

        case 'progress':
            return [
                'show_badge' => ! empty( $raw['show_badge'] ),
                'show_score' => ! empty( $raw['show_score'] ),
                'show_task'  => ! empty( $raw['show_task'] ),
            ];

        case 'rss_feed':
            return [
                'feed_url'    => esc_url_raw( $raw['feed_url']    ?? '' ),
                'feed_limit'  => max( 1, min( 20, (int) ( $raw['feed_limit'] ?? 10 ) ) ),
                'feed_prefix' => sanitize_text_field( $raw['feed_prefix'] ?? '' ),
                'badge_label' => sanitize_text_field( $raw['badge_label'] ?? 'RSS NEWS' ),
                'cta_text'    => sanitize_text_field( $raw['cta_text']    ?? 'Read Full Story' ),
                'link_out'    => ! empty( $raw['link_out'] ),
            ];

        default:
            return [];
    }
}


// ─── ADMIN PAGE ───────────────────────────────────────────────────────────────

function mfsd_hw_render_admin_page(): void {
    $view = sanitize_key( $_GET['view'] ?? 'list' );
    $msg  = sanitize_key( $_GET['msg']  ?? '' );

    $notices = [
        'saved'   => [ 'success', __( 'Widget saved.', 'mfsd-home-widgets' ) ],
        'deleted' => [ 'success', __( 'Widget deleted.', 'mfsd-home-widgets' ) ],
        'toggled' => [ 'success', __( 'Widget status updated.', 'mfsd-home-widgets' ) ],
    ];
    ?>
    <div class="wrap mfsd-hw-admin">
      <h1 class="mfsd-hw-admin__title">
        <span class="dashicons dashicons-grid-view"></span>
        <?php esc_html_e( 'MFSD Home Widgets', 'mfsd-home-widgets' ); ?>
        <?php if ( $view === 'list' ) : ?>
          <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'mfsd-home-widgets', 'view' => 'add' ], admin_url( 'admin.php' ) ) ); ?>"
             class="page-title-action">
            <?php esc_html_e( '+ Add Widget', 'mfsd-home-widgets' ); ?>
          </a>
        <?php endif; ?>
      </h1>

      <?php if ( isset( $notices[ $msg ] ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notices[$msg][0] ); ?> is-dismissible">
          <p><?php echo esc_html( $notices[$msg][1] ); ?></p>
        </div>
      <?php endif; ?>

      <?php if ( $view === 'list' ) : ?>
        <?php mfsd_hw_render_list(); ?>
      <?php elseif ( $view === 'add' ) : ?>
        <?php mfsd_hw_render_type_picker(); ?>
      <?php elseif ( $view === 'new' ) : ?>
        <?php mfsd_hw_render_form( null, sanitize_key( $_GET['type'] ?? '' ) ); ?>
      <?php elseif ( $view === 'edit' ) : ?>
        <?php
        $id     = (int) ( $_GET['id'] ?? 0 );
        $widget = $id ? mfsd_hw_get( $id ) : null;
        if ( $widget ) {
            mfsd_hw_render_form( $widget, $widget['type'] );
        } else {
            echo '<p>' . esc_html__( 'Widget not found.', 'mfsd-home-widgets' ) . '</p>';
        }
        ?>
      <?php endif; ?>
    </div>
    <?php
}


// ─── LIST VIEW ────────────────────────────────────────────────────────────────

function mfsd_hw_render_list(): void {
    $widgets = mfsd_hw_get_all();
    $types   = mfsd_hw_widget_types();
    $roles   = mfsd_hw_roles();
    ?>
    <p class="description" style="margin:12px 0 16px;">
      <?php esc_html_e( 'Each row is one widget instance on the home page. You can add the same widget type multiple times. Drag to reorder is coming — for now use the Sort Order field.', 'mfsd-home-widgets' ); ?>
    </p>

    <?php if ( empty( $widgets ) ) : ?>
      <p><?php esc_html_e( 'No widgets yet. Click "+ Add Widget" to create your first one.', 'mfsd-home-widgets' ); ?></p>
    <?php else : ?>

      <table class="widefat mfsd-hw-admin__list-table">
        <thead>
          <tr>
            <th style="width:50px;"><?php esc_html_e( 'Order', 'mfsd-home-widgets' ); ?></th>
            <th><?php esc_html_e( 'Label', 'mfsd-home-widgets' ); ?></th>
            <th><?php esc_html_e( 'Type', 'mfsd-home-widgets' ); ?></th>
            <th><?php esc_html_e( 'Visible to', 'mfsd-home-widgets' ); ?></th>
            <th style="width:80px;"><?php esc_html_e( 'Status', 'mfsd-home-widgets' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'mfsd-home-widgets' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $widgets as $w ) :
            $is_active   = (int) $w['active'] === 1;
            $type_info   = $types[ $w['type'] ] ?? [ 'label' => $w['type'], 'icon' => 'dashicons-admin-generic' ];
            $role_labels = array_map( fn( $r ) => $roles[$r] ?? $r, (array) $w['roles'] );
          ?>
            <tr class="mfsd-hw-admin__list-row<?php echo $is_active ? '' : ' mfsd-hw-admin__list-row--paused'; ?>">

              <td class="mfsd-hw-admin__list-order"><?php echo esc_html( $w['sort_order'] ); ?></td>

              <td>
                <strong><?php echo esc_html( $w['label'] ?: $type_info['label'] ); ?></strong>
                <div style="color:#888;font-size:12px;">ID: <?php echo (int) $w['id']; ?></div>
              </td>

              <td>
                <span class="dashicons <?php echo esc_attr( $type_info['icon'] ); ?>"></span>
                <?php echo esc_html( $type_info['label'] ); ?>
              </td>

              <td>
                <?php foreach ( $role_labels as $rl ) : ?>
                  <span class="mfsd-hw-admin__role-pill"><?php echo esc_html( $rl ); ?></span>
                <?php endforeach; ?>
              </td>

              <td>
                <span class="mfsd-hw-admin__status mfsd-hw-admin__status--<?php echo $is_active ? 'live' : 'off'; ?>">
                  <?php echo $is_active ? 'Live' : 'Off'; ?>
                </span>
              </td>

              <td class="mfsd-hw-admin__list-actions">
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'mfsd-home-widgets', 'view' => 'edit', 'id' => $w['id'] ], admin_url( 'admin.php' ) ) ); ?>"
                   class="button button-small">
                  <?php esc_html_e( 'Edit', 'mfsd-home-widgets' ); ?>
                </a>
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'mfsd_hw_toggle', 'id' => $w['id'] ], admin_url( 'admin-post.php' ) ), 'mfsd_hw_toggle' ) ); ?>"
                   class="button button-small">
                  <?php echo $is_active ? esc_html__( 'Pause', 'mfsd-home-widgets' ) : esc_html__( 'Activate', 'mfsd-home-widgets' ); ?>
                </a>
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'mfsd_hw_delete', 'id' => $w['id'] ], admin_url( 'admin-post.php' ) ), 'mfsd_hw_delete' ) ); ?>"
                   class="button button-small mfsd-hw-admin__btn-delete"
                   onclick="return confirm('<?php esc_attr_e( 'Delete this widget? This cannot be undone.', 'mfsd-home-widgets' ); ?>')">
                  <?php esc_html_e( 'Delete', 'mfsd-home-widgets' ); ?>
                </a>
              </td>

            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php endif; ?>
    <?php
}


// ─── TYPE PICKER ──────────────────────────────────────────────────────────────

function mfsd_hw_render_type_picker(): void {
    $types = mfsd_hw_widget_types();
    ?>
    <h2><?php esc_html_e( 'Choose a Widget Type', 'mfsd-home-widgets' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Select the type of widget you want to add. You can add the same type multiple times.', 'mfsd-home-widgets' ); ?></p>

    <div class="mfsd-hw-admin__type-grid">
      <?php foreach ( $types as $slug => $info ) :
        $url = add_query_arg( [ 'page' => 'mfsd-home-widgets', 'view' => 'new', 'type' => $slug ], admin_url( 'admin.php' ) );
      ?>
        <a href="<?php echo esc_url( $url ); ?>" class="mfsd-hw-admin__type-card">
          <span class="dashicons <?php echo esc_attr( $info['icon'] ); ?>"></span>
          <strong><?php echo esc_html( $info['label'] ); ?></strong>
          <span><?php echo esc_html( $info['description'] ); ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <p>
      <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'mfsd-home-widgets' ], admin_url( 'admin.php' ) ) ); ?>"
         class="button">
        ← <?php esc_html_e( 'Back to widget list', 'mfsd-home-widgets' ); ?>
      </a>
    </p>
    <?php
}


// ─── ADD / EDIT FORM ──────────────────────────────────────────────────────────

function mfsd_hw_render_form( ?array $widget, string $type ): void {
    $types    = mfsd_hw_widget_types();
    $all_roles = mfsd_hw_roles();

    if ( ! array_key_exists( $type, $types ) ) {
        echo '<p>' . esc_html__( 'Invalid widget type.', 'mfsd-home-widgets' ) . '</p>';
        return;
    }

    $is_edit     = $widget !== null;
    $type_info   = $types[ $type ];
    $saved_roles = $widget ? (array) $widget['roles'] : [ 'all' ];
    $config      = $widget ? (array) $widget['config'] : [];
    $label       = $widget ? $widget['label'] : $type_info['label'];
    $sort_order  = $widget ? (int) $widget['sort_order'] : 0;
    $active      = $widget ? (bool) $widget['active'] : true;
    $widget_id   = $widget ? (int) $widget['id'] : 0;
    ?>

    <h2>
      <span class="dashicons <?php echo esc_attr( $type_info['icon'] ); ?>"></span>
      <?php echo $is_edit
          ? esc_html__( 'Edit Widget', 'mfsd-home-widgets' ) . ': ' . esc_html( $label )
          : esc_html__( 'Add Widget', 'mfsd-home-widgets' ) . ': ' . esc_html( $type_info['label'] );
      ?>
    </h2>

    <p>
      <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'mfsd-home-widgets' ], admin_url( 'admin.php' ) ) ); ?>"
         class="button">
        ← <?php esc_html_e( 'Back to widget list', 'mfsd-home-widgets' ); ?>
      </a>
    </p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'mfsd_hw_save' ); ?>
      <input type="hidden" name="action"    value="mfsd_hw_save">
      <input type="hidden" name="type"      value="<?php echo esc_attr( $type ); ?>">
      <input type="hidden" name="widget_id" value="<?php echo esc_attr( $widget_id ); ?>">

      <div class="mfsd-hw-admin__form-layout">

        <?php /* Left: content */ ?>
        <div class="mfsd-hw-admin__form-main">

          <div class="mfsd-hw-admin__form-section">
            <h3><?php esc_html_e( 'Widget Identity', 'mfsd-home-widgets' ); ?></h3>

            <div class="mfsd-hw-admin__field">
              <label for="mfsd_hw_label"><?php esc_html_e( 'Admin Label', 'mfsd-home-widgets' ); ?></label>
              <input type="text"
                     id="mfsd_hw_label"
                     name="label"
                     value="<?php echo esc_attr( $label ); ?>"
                     placeholder="e.g. Student Internal News #1"
                     style="width:100%;max-width:500px;">
              <p class="description"><?php esc_html_e( 'Used only in the admin list — not shown on the front end.', 'mfsd-home-widgets' ); ?></p>
            </div>
          </div>

          <div class="mfsd-hw-admin__form-section">
            <h3><?php esc_html_e( 'Content', 'mfsd-home-widgets' ); ?></h3>
            <?php mfsd_hw_render_config_fields( $type, $config ); ?>
          </div>

        </div>

        <?php /* Right: settings sidebar */ ?>
        <div class="mfsd-hw-admin__form-sidebar">

          <div class="mfsd-hw-admin__sidebar-box">
            <h3><?php esc_html_e( 'Publish', 'mfsd-home-widgets' ); ?></h3>
            <label class="mfsd-hw-admin__check">
              <input type="checkbox" name="active" value="1" <?php checked( $active ); ?>>
              <?php esc_html_e( 'Active (live on site)', 'mfsd-home-widgets' ); ?>
            </label>
            <hr>
            <button type="submit" class="button button-primary button-large" style="width:100%;margin-top:8px;">
              <?php echo $is_edit ? esc_html__( 'Update Widget', 'mfsd-home-widgets' ) : esc_html__( 'Add Widget', 'mfsd-home-widgets' ); ?>
            </button>
          </div>

          <div class="mfsd-hw-admin__sidebar-box">
            <h3><?php esc_html_e( 'Grid Position', 'mfsd-home-widgets' ); ?></h3>
            <label for="mfsd_hw_order"><?php esc_html_e( 'Sort Order', 'mfsd-home-widgets' ); ?></label>
            <input type="number" id="mfsd_hw_order" name="sort_order"
                   value="<?php echo esc_attr( $sort_order ); ?>"
                   min="0" max="999" style="width:80px;display:block;margin-top:6px;">
            <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Lower = appears earlier in the grid.', 'mfsd-home-widgets' ); ?></p>
          </div>

          <div class="mfsd-hw-admin__sidebar-box">
            <h3><?php esc_html_e( 'Visible to', 'mfsd-home-widgets' ); ?></h3>
            <p class="description" style="margin-bottom:10px;"><?php esc_html_e( 'Which roles can see this widget.', 'mfsd-home-widgets' ); ?></p>
            <?php foreach ( $all_roles as $slug => $rlabel ) : ?>
              <label class="mfsd-hw-admin__check" style="margin-bottom:8px;">
                <input type="checkbox" name="roles[]" value="<?php echo esc_attr( $slug ); ?>"
                       <?php checked( in_array( $slug, $saved_roles, true ) ); ?>>
                <?php echo esc_html( $rlabel ); ?>
              </label>
            <?php endforeach; ?>
          </div>

        </div>

      </div>

    </form>
    <?php
}


// ─── CONFIG FIELD RENDERERS ───────────────────────────────────────────────────

function mfsd_hw_render_config_fields( string $type, array $config ): void {
    switch ( $type ) {
        case 'news_internal':
        case 'news_external':
            // ── Multi-item carousel (up to 10 articles) ──
            // Backward compat: if old flat config, wrap in items array.
            if ( isset( $config['items'] ) && is_array( $config['items'] ) ) {
                $items = $config['items'];
            } elseif ( ! empty( $config['headline'] ) || ! empty( $config['image_id'] ) ) {
                $items = [ [
                    'headline' => $config['headline'] ?? '',
                    'summary'  => $config['summary']  ?? '',
                    'image_id' => $config['image_id'] ?? 0,
                    'link'     => $config['link']     ?? '',
                    'cta_text' => $config['cta_text'] ?? 'Read More',
                ] ];
            } else {
                $items = [ [ 'headline' => '', 'summary' => '', 'image_id' => 0, 'link' => '', 'cta_text' => 'Read More' ] ];
            }
            ?>
            <div class="mfsd-hw-admin__info-box" style="margin-bottom:20px;">
              <?php printf(
                  esc_html__( 'Add up to 10 articles. With 2 or more, the widget becomes a carousel that rotates every 5 seconds. Currently: %d article(s).', 'mfsd-home-widgets' ),
                  count( $items )
              ); ?>
            </div>

            <div id="mfsd-hw-items-container">
              <?php foreach ( $items as $idx => $item ) : ?>
                <?php mfsd_hw_render_news_item_fields( $idx, $item, $type ); ?>
              <?php endforeach; ?>
            </div>

            <?php if ( count( $items ) < 10 ) : ?>
              <button type="button" id="mfsd-hw-add-item" class="button" style="margin-top:12px;"
                      data-type="<?php echo esc_attr( $type ); ?>">
                + <?php esc_html_e( 'Add Article', 'mfsd-home-widgets' ); ?>
              </button>
            <?php endif; ?>
            <?php
            break;

        case 'new_courses':
            mfsd_hw_text_field(     'config[headline]', __( 'Headline',      'mfsd-home-widgets' ), $config['headline'] ?? '' );
            mfsd_hw_textarea_field( 'config[summary]',  __( 'Summary',       'mfsd-home-widgets' ), $config['summary']  ?? '' );
            mfsd_hw_image_field(    'config[image_id]', __( 'Image',         'mfsd-home-widgets' ), (int) ( $config['image_id'] ?? 0 ) );
            mfsd_hw_page_field( 'config[link]', __( 'Link to Page', 'mfsd-home-widgets' ), $config['link'] ?? '' );
            mfsd_hw_text_field( 'config[cta_text]', __( 'Button Label', 'mfsd-home-widgets' ), $config['cta_text'] ?? 'Course Details' );
            break;

        case 'shorts':
            mfsd_hw_text_field(  'config[title]',     __( 'Video Title',          'mfsd-home-widgets' ), $config['title']     ?? '' );
            mfsd_hw_text_field(  'config[video_url]', __( 'Video URL',            'mfsd-home-widgets' ), $config['video_url'] ?? '', 'url' );
            mfsd_hw_image_field( 'config[image_id]',  __( 'Thumbnail Image',      'mfsd-home-widgets' ), (int) ( $config['image_id'] ?? 0 ) );
            mfsd_hw_text_field(  'config[duration]',  __( 'Duration (e.g. 0:28)', 'mfsd-home-widgets' ), $config['duration']  ?? '' );
            mfsd_hw_text_field(  'config[cta_text]',  __( 'Button Label',         'mfsd-home-widgets' ), $config['cta_text']  ?? 'Watch Now' );
            break;

        case 'top_scores':
            ?>
            <div class="mfsd-hw-admin__field">
              <label><?php esc_html_e( 'Games', 'mfsd-home-widgets' ); ?></label>
              <select name="config[games]">
                <option value="all"       <?php selected( $config['games'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All games', 'mfsd-home-widgets' ); ?></option>
                <option value="asteroids" <?php selected( $config['games'] ?? '', 'asteroids' ); ?>>Asteroids</option>
                <option value="mario"     <?php selected( $config['games'] ?? '', 'mario' ); ?>>Infinite Mario</option>
                <option value="hgc"       <?php selected( $config['games'] ?? '', 'hgc' ); ?>>Hyperspace Garbage Collection</option>
              </select>
            </div>
            <div class="mfsd-hw-admin__field">
              <label><?php esc_html_e( 'Number of scores to show', 'mfsd-home-widgets' ); ?></label>
              <select name="config[score_count]">
                <?php foreach ( [ 1, 3, 5, 10 ] as $n ) : ?>
                  <option value="<?php echo $n; ?>" <?php selected( (int) ( $config['score_count'] ?? 5 ), $n ); ?>>
                    <?php printf( esc_html__( 'Top %d', 'mfsd-home-widgets' ), $n ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mfsd-hw-admin__field">
              <label><?php esc_html_e( 'Score mode', 'mfsd-home-widgets' ); ?></label>
              <label class="mfsd-hw-admin__check">
                <input type="radio" name="config[mode]" value="global" <?php checked( $config['mode'] ?? 'global', 'global' ); ?>>
                <?php esc_html_e( 'Global leaderboard (all students)', 'mfsd-home-widgets' ); ?>
              </label>
              <label class="mfsd-hw-admin__check" style="margin-top:6px;">
                <input type="radio" name="config[mode]" value="student" <?php checked( $config['mode'] ?? 'global', 'student' ); ?>>
                <?php esc_html_e( "Student's own scores (profile view)", 'mfsd-home-widgets' ); ?>
              </label>
            </div>
            <?php
            break;

        case 'progress':
            ?>
            <div class="mfsd-hw-admin__info-box">
              <strong><?php esc_html_e( 'Student view:', 'mfsd-home-widgets' ); ?></strong>
              <?php esc_html_e( "Shows the student's own latest badge, top score and most recent task.", 'mfsd-home-widgets' ); ?>
              <br><br>
              <strong><?php esc_html_e( 'Parent view:', 'mfsd-home-widgets' ); ?></strong>
              <?php esc_html_e( "Shows a summary of the last task their linked student completed.", 'mfsd-home-widgets' ); ?>
            </div>
            <div class="mfsd-hw-admin__field" style="margin-top:16px;">
              <label><?php esc_html_e( 'Show in student view', 'mfsd-home-widgets' ); ?></label>
              <label class="mfsd-hw-admin__check"><input type="checkbox" name="config[show_badge]" value="1" <?php checked( ! empty( $config['show_badge'] ) ); ?>> <?php esc_html_e( 'Latest badge earned', 'mfsd-home-widgets' ); ?></label>
              <label class="mfsd-hw-admin__check"><input type="checkbox" name="config[show_score]" value="1" <?php checked( ! empty( $config['show_score'] ) ); ?>> <?php esc_html_e( 'Top arcade score', 'mfsd-home-widgets' ); ?></label>
              <label class="mfsd-hw-admin__check"><input type="checkbox" name="config[show_task]"  value="1" <?php checked( ! empty( $config['show_task'] ) ); ?>>  <?php esc_html_e( 'Latest task summary', 'mfsd-home-widgets' ); ?></label>
            </div>
            <?php
            break;
    }
}


// ─── FIELD HELPERS ────────────────────────────────────────────────────────────

function mfsd_hw_text_field( string $name, string $label, string $value, string $type = 'text' ): void { ?>
    <div class="mfsd-hw-admin__field">
      <label><?php echo esc_html( $label ); ?></label>
      <input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>"
             value="<?php echo esc_attr( $value ); ?>" style="width:100%;max-width:560px;">
    </div>
<?php }

function mfsd_hw_textarea_field( string $name, string $label, string $value ): void { ?>
    <div class="mfsd-hw-admin__field">
      <label><?php echo esc_html( $label ); ?></label>
      <textarea name="<?php echo esc_attr( $name ); ?>" rows="4"
                style="width:100%;max-width:560px;font-size:13px;padding:6px;resize:vertical;"><?php echo esc_textarea( $value ); ?></textarea>
    </div>
<?php }

function mfsd_hw_image_field( string $name, string $label, int $image_id ): void {
    $uid     = 'mfsd_hw_img_' . md5( $name );
    $preview = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
    ?>
    <div class="mfsd-hw-admin__field">
      <label><?php echo esc_html( $label ); ?></label>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <img id="<?php echo esc_attr( $uid ); ?>_preview"
             src="<?php echo esc_url( $preview ); ?>"
             style="height:60px;width:auto;max-width:120px;border:1px solid #ccd0d4;border-radius:3px;<?php echo $preview ? '' : 'display:none;'; ?>">
        <input type="hidden" name="<?php echo esc_attr( $name ); ?>"
               id="<?php echo esc_attr( $uid ); ?>" value="<?php echo esc_attr( $image_id ); ?>">
        <button type="button" class="button mfsd-hw-media-btn"
                data-target="<?php echo esc_attr( $uid ); ?>"
                data-preview="<?php echo esc_attr( $uid ); ?>_preview">
          <?php echo $image_id > 0 ? esc_html__( 'Change Image', 'mfsd-home-widgets' ) : esc_html__( 'Select Image', 'mfsd-home-widgets' ); ?>
        </button>
        <?php if ( $image_id > 0 ) : ?>
          <button type="button" class="button mfsd-hw-media-clear"
                  data-target="<?php echo esc_attr( $uid ); ?>"
                  data-preview="<?php echo esc_attr( $uid ); ?>_preview">
            <?php esc_html_e( 'Remove', 'mfsd-home-widgets' ); ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

/**
 * Page dropdown field — lists all published pages as options.
 * Stores the page permalink as the value so it works directly as a link href.
 *
 * @param string $name   Field name attribute.
 * @param string $label  Field label.
 * @param string $value  Currently saved URL (used to re-select the matching page).
 */
function mfsd_hw_page_field( string $name, string $label, string $value ): void {
    // Get all published pages.
    $pages = get_pages( [
        'sort_column' => 'post_title',
        'sort_order'  => 'ASC',
        'post_status' => 'publish',
    ] );
    ?>
    <div class="mfsd-hw-admin__field">
      <label><?php echo esc_html( $label ); ?></label>
      <select name="<?php echo esc_attr( $name ); ?>" style="max-width:560px;">
        <option value=""><?php esc_html_e( '— Select a page —', 'mfsd-home-widgets' ); ?></option>
        <?php foreach ( $pages as $page ) :
          $permalink = get_permalink( $page->ID );
        ?>
          <option value="<?php echo esc_url( $permalink ); ?>"
                  <?php selected( $value, $permalink ); ?>>
            <?php echo esc_html( $page->post_title ); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description">
        <?php esc_html_e( 'Select the page this widget card should link to.', 'mfsd-home-widgets' ); ?>
      </p>
    </div>
    <?php
}


// ─── NEWS ITEM REPEATABLE FIELDS ──────────────────────────────────────────────

/**
 * Renders one article block inside the multi-item news form.
 *
 * @param int    $idx   Zero-based index for the item.
 * @param array  $item  Item data (headline, summary, image_id, link, cta_text).
 * @param string $type  Widget type (news_internal or news_external).
 */
function mfsd_hw_render_news_item_fields( int $idx, array $item, string $type ): void {
    $prefix = "config[items][{$idx}]";
    $uid    = 'mfsd_hw_item_img_' . $idx;
    $preview = ( (int) ( $item['image_id'] ?? 0 ) ) > 0
        ? wp_get_attachment_image_url( (int) $item['image_id'], 'thumbnail' ) : '';
    ?>
    <div class="mfsd-hw-admin__item-block" data-item-index="<?php echo $idx; ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <strong style="font-size:14px;">
          <?php printf( esc_html__( 'Article %d', 'mfsd-home-widgets' ), $idx + 1 ); ?>
        </strong>
        <?php if ( $idx > 0 ) : ?>
          <button type="button" class="button mfsd-hw-remove-item"
                  style="color:#b32d2e;border-color:#b32d2e;">
            <?php esc_html_e( 'Remove', 'mfsd-home-widgets' ); ?>
          </button>
        <?php endif; ?>
      </div>

      <div class="mfsd-hw-admin__field">
        <label><?php esc_html_e( 'Headline', 'mfsd-home-widgets' ); ?></label>
        <input type="text" name="<?php echo esc_attr( $prefix ); ?>[headline]"
               value="<?php echo esc_attr( $item['headline'] ?? '' ); ?>"
               style="width:100%;max-width:560px;">
      </div>

      <div class="mfsd-hw-admin__field">
        <label><?php esc_html_e( 'Summary', 'mfsd-home-widgets' ); ?></label>
        <textarea name="<?php echo esc_attr( $prefix ); ?>[summary]" rows="3"
                  style="width:100%;max-width:560px;font-size:13px;padding:6px;resize:vertical;"><?php echo esc_textarea( $item['summary'] ?? '' ); ?></textarea>
      </div>

      <div class="mfsd-hw-admin__field">
        <label><?php esc_html_e( 'Image', 'mfsd-home-widgets' ); ?></label>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <img id="<?php echo esc_attr( $uid ); ?>_preview"
               src="<?php echo esc_url( $preview ); ?>"
               style="height:60px;width:auto;max-width:120px;border:1px solid #ccd0d4;border-radius:3px;<?php echo $preview ? '' : 'display:none;'; ?>">
          <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[image_id]"
                 id="<?php echo esc_attr( $uid ); ?>"
                 value="<?php echo esc_attr( (int) ( $item['image_id'] ?? 0 ) ); ?>">
          <button type="button" class="button mfsd-hw-media-btn"
                  data-target="<?php echo esc_attr( $uid ); ?>"
                  data-preview="<?php echo esc_attr( $uid ); ?>_preview">
            <?php echo ( (int) ( $item['image_id'] ?? 0 ) ) > 0
                ? esc_html__( 'Change Image', 'mfsd-home-widgets' )
                : esc_html__( 'Select Image', 'mfsd-home-widgets' ); ?>
          </button>
          <button type="button" class="button mfsd-hw-media-clear"
                  data-target="<?php echo esc_attr( $uid ); ?>"
                  data-preview="<?php echo esc_attr( $uid ); ?>_preview"
                  style="<?php echo ( (int) ( $item['image_id'] ?? 0 ) ) > 0 ? '' : 'display:none;'; ?>">
            <?php esc_html_e( 'Remove', 'mfsd-home-widgets' ); ?>
          </button>
        </div>
      </div>

      <?php if ( $type === 'news_external' ) : ?>
        <div class="mfsd-hw-admin__field">
          <label><?php esc_html_e( 'External URL', 'mfsd-home-widgets' ); ?></label>
          <input type="url" name="<?php echo esc_attr( $prefix ); ?>[link]"
                 value="<?php echo esc_attr( $item['link'] ?? '' ); ?>"
                 style="width:100%;max-width:560px;">
        </div>
      <?php else : ?>
        <?php mfsd_hw_page_field( $prefix . '[link]', __( 'Link to Page', 'mfsd-home-widgets' ), $item['link'] ?? '' ); ?>
      <?php endif; ?>

      <div class="mfsd-hw-admin__field">
        <label><?php esc_html_e( 'Button Label', 'mfsd-home-widgets' ); ?></label>
        <input type="text" name="<?php echo esc_attr( $prefix ); ?>[cta_text]"
               value="<?php echo esc_attr( $item['cta_text'] ?? 'Read More' ); ?>"
               style="width:100%;max-width:560px;">
      </div>

      <hr style="border:none;border-top:2px solid #C9A84C;margin:20px 0;">
    </div>
    <?php
}