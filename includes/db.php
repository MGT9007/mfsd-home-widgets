<?php
/**
 * MFSD Home Widgets — Database Layer
 *
 * Table: {prefix}mfsd_hw_widgets
 *
 * One row per widget instance. The same widget type can appear multiple times.
 *
 * Columns:
 *   id          INT AUTO_INCREMENT PRIMARY KEY
 *   type        VARCHAR(50)   — widget type slug (news_internal, shorts, etc.)
 *   label       VARCHAR(200)  — admin-facing label, e.g. "Student Internal News #1"
 *   roles       VARCHAR(500)  — JSON array, e.g. ["student"] or ["all"]
 *   active      TINYINT(1)    — 1 = live, 0 = hidden
 *   sort_order  INT           — grid position (lower = earlier)
 *   config      LONGTEXT      — JSON: type-specific content and settings
 *   created_at  DATETIME
 *   updated_at  DATETIME
 */

defined( 'ABSPATH' ) || exit;


// ─── TABLE MANAGEMENT ─────────────────────────────────────────────────────────

function mfsd_hw_create_table(): void {
    global $wpdb;

    $table           = $wpdb->prefix . MFSD_HW_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        type        VARCHAR(50)   NOT NULL,
        label       VARCHAR(200)  NOT NULL DEFAULT '',
        roles       VARCHAR(500)  NOT NULL DEFAULT '[\"all\"]',
        active      TINYINT(1)    NOT NULL DEFAULT 1,
        sort_order  INT           NOT NULL DEFAULT 0,
        config      LONGTEXT      NOT NULL DEFAULT '{}',
        created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY type (type),
        KEY sort_order (sort_order)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'mfsd_hw_db_version', MFSD_HW_VERSION );
}

/**
 * Seed three starter instances on fresh activation so the grid isn't empty.
 */
function mfsd_hw_seed_defaults(): void {
    global $wpdb;
    $table = $wpdb->prefix . MFSD_HW_TABLE;
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    if ( $count > 0 ) return;

    $defaults = [
        [
            'type'       => 'news_internal',
            'label'      => 'MFS News',
            'roles'      => [ 'all' ],
            'active'     => 1,
            'sort_order' => 1,
            'config'     => [
                'headline' => 'Welcome to My Future Self Digital',
                'summary'  => 'The MFS online course has been carefully designed to capture the 7 core topics Steve has been teaching to students across the UK.',
                'image_id' => 0,
                'link'     => home_url( '/' ),
                'cta_text' => 'Read More',
            ],
        ],
        [
            'type'       => 'top_scores',
            'label'      => 'Top Scores',
            'roles'      => [ 'all' ],
            'active'     => 1,
            'sort_order' => 2,
            'config'     => [
                'games'       => 'all',
                'score_count' => 5,
                'mode'        => 'global',
            ],
        ],
        [
            'type'       => 'progress',
            'label'      => 'Progress & Achievements',
            'roles'      => [ 'all' ],
            'active'     => 1,
            'sort_order' => 3,
            'config'     => [
                'show_badge' => true,
                'show_score' => true,
                'show_task'  => true,
            ],
        ],
    ];

    foreach ( $defaults as $d ) {
        $wpdb->insert( $table, [
            'type'       => $d['type'],
            'label'      => $d['label'],
            'roles'      => json_encode( $d['roles'] ),
            'active'     => $d['active'],
            'sort_order' => $d['sort_order'],
            'config'     => json_encode( $d['config'] ),
        ], [ '%s', '%s', '%s', '%d', '%d', '%s' ] );
    }
}


// ─── CRUD ─────────────────────────────────────────────────────────────────────

/**
 * Get all widget instances ordered by sort_order.
 *
 * @return array[]
 */
function mfsd_hw_get_all(): array {
    global $wpdb;
    $table = $wpdb->prefix . MFSD_HW_TABLE;
    $rows  = $wpdb->get_results(
        "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC",
        ARRAY_A
    ) ?: [];
    return array_map( 'mfsd_hw_decode_row', $rows );
}

/**
 * Get a single widget instance.
 *
 * @param int $id
 * @return array|null
 */
function mfsd_hw_get( int $id ): ?array {
    global $wpdb;
    $table = $wpdb->prefix . MFSD_HW_TABLE;
    $row   = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
        ARRAY_A
    );
    return $row ? mfsd_hw_decode_row( $row ) : null;
}

/**
 * Get all active instances visible to a specific role, ordered by sort_order.
 *
 * @param string $role  e.g. 'student', 'parent', 'admin'
 * @return array[]
 */
function mfsd_hw_get_for_role( string $role ): array {
    global $wpdb;
    $table = $wpdb->prefix . MFSD_HW_TABLE;
    $rows  = $wpdb->get_results(
        "SELECT * FROM {$table} WHERE active = 1 ORDER BY sort_order ASC, id ASC",
        ARRAY_A
    ) ?: [];

    $visible = [];
    foreach ( $rows as $row ) {
        $roles = json_decode( $row['roles'], true ) ?: [ 'all' ];
        if ( in_array( 'all', $roles, true ) || in_array( $role, $roles, true ) ) {
            $visible[] = mfsd_hw_decode_row( $row );
        }
    }
    return $visible;
}

/**
 * Insert a new widget instance.
 *
 * @param array $data  Keys: type, label, roles (array), active, sort_order, config (array)
 * @return int|false   New ID or false on failure.
 */
function mfsd_hw_insert( array $data ) {
    global $wpdb;
    $table  = $wpdb->prefix . MFSD_HW_TABLE;
    $result = $wpdb->insert( $table, [
        'type'       => $data['type'],
        'label'      => $data['label']      ?? '',
        'roles'      => json_encode( $data['roles'] ?? [ 'all' ] ),
        'active'     => (int) ( $data['active'] ?? 1 ),
        'sort_order' => (int) ( $data['sort_order'] ?? 0 ),
        'config'     => json_encode( $data['config'] ?? [] ),
    ], [ '%s', '%s', '%s', '%d', '%d', '%s' ] );
    return $result ? $wpdb->insert_id : false;
}

/**
 * Update an existing widget instance.
 *
 * @param int   $id
 * @param array $data
 * @return bool
 */
function mfsd_hw_update( int $id, array $data ): bool {
    global $wpdb;
    $table  = $wpdb->prefix . MFSD_HW_TABLE;
    $result = $wpdb->update( $table, [
        'type'       => $data['type'],
        'label'      => $data['label']      ?? '',
        'roles'      => json_encode( $data['roles'] ?? [ 'all' ] ),
        'active'     => (int) ( $data['active'] ?? 1 ),
        'sort_order' => (int) ( $data['sort_order'] ?? 0 ),
        'config'     => json_encode( $data['config'] ?? [] ),
    ], [ 'id' => $id ],
    [ '%s', '%s', '%s', '%d', '%d', '%s' ],
    [ '%d' ] );
    return $result !== false;
}

/**
 * Delete a widget instance.
 *
 * @param int $id
 * @return bool
 */
function mfsd_hw_delete( int $id ): bool {
    global $wpdb;
    $table = $wpdb->prefix . MFSD_HW_TABLE;
    return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
}

/**
 * Decode JSON fields in a database row.
 *
 * @param array $row
 * @return array
 */
function mfsd_hw_decode_row( array $row ): array {
    $row['roles']  = json_decode( $row['roles'],  true ) ?: [ 'all' ];
    $row['config'] = json_decode( $row['config'], true ) ?: [];
    return $row;
}

/**
 * Get a WordPress attachment image URL by ID, with SVG placeholder fallback.
 *
 * @param int    $id
 * @param string $size
 * @return string
 */
function mfsd_hw_get_image_url( int $id, string $size = 'medium' ): string {
    if ( $id > 0 ) {
        $src = wp_get_attachment_image_url( $id, $size );
        if ( $src ) return $src;
    }
    return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="220"%3E%3Crect width="400" height="220" fill="%23222"%3E%3C/rect%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" fill="%23555" font-size="13" font-family="Arial"%3ENo image%3C/text%3E%3C/svg%3E';
}
