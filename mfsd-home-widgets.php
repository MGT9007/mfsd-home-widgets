<?php
/**
 * Plugin Name:  MFSD Home Widgets
 * Plugin URI:   https://mfsd.me
 * Description:  Manages and displays the role-aware home page widget grid
 *               for My Future Self Digital. Fully instance-based — you can
 *               create as many widgets as you need, of any type, in any order,
 *               visible to any role combination. Six widget types available:
 *               MFS News (Internal), External News, Shorts Video, New Courses,
 *               Top Scores, and Progress & Achievements.
 * Version:      5.11.6
 * Author:       MisterT9007
 * Author URI:   https://s47d.co.uk
 * Text Domain:  mfsd-home-widgets
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

// ─── CONSTANTS ───────────────────────────────────────────────────────────────

define( 'MFSD_HW_VERSION', '5.11.6' );
define( 'MFSD_HW_DIR',     plugin_dir_path( __FILE__ ) );
define( 'MFSD_HW_URI',     plugin_dir_url( __FILE__ ) );
define( 'MFSD_HW_TABLE',   'mfsd_hw_widgets' );

// ─── WIDGET TYPE REGISTRY ─────────────────────────────────────────────────────

/**
 * All available widget types.
 * slug => [ label, icon, description ]
 */
function mfsd_hw_widget_types(): array {
    return [
        'news_internal' => [
            'label'       => __( 'MFS News (Internal)', 'mfsd-home-widgets' ),
            'icon'        => 'dashicons-admin-post',
            'description' => __( 'Internal news card — headline, image, summary, link to an internal page.', 'mfsd-home-widgets' ),
        ],
        'news_external' => [
            'label'       => __( 'External News / Article', 'mfsd-home-widgets' ),
            'icon'        => 'dashicons-external',
            'description' => __( 'External news card — headline, image, summary, link to an external URL.', 'mfsd-home-widgets' ),
        ],
        'shorts' => [
            'label'       => __( 'Shorts Video', 'mfsd-home-widgets' ),
            'icon'        => 'dashicons-video-alt3',
            'description' => __( 'Short video card (max 30 seconds). Links to a video URL or YouTube/Vimeo.', 'mfsd-home-widgets' ),
        ],
        'new_courses' => [
            'label'       => __( 'New Course', 'mfsd-home-widgets' ),
            'icon'        => 'dashicons-welcome-learn-more',
            'description' => __( 'Promotes a new or upcoming course with headline, image and link.', 'mfsd-home-widgets' ),
        ],
        'top_scores' => [
            'label'       => __( 'Top Scores / Leaderboard', 'mfsd-home-widgets' ),
            'icon'        => 'dashicons-chart-bar',
            'description' => __( 'Shows top arcade scores — global leaderboard or individual student view.', 'mfsd-home-widgets' ),
        ],
        'progress' => [
            'label'       => __( 'Progress & Achievements', 'mfsd-home-widgets' ),
            'icon'        => 'dashicons-awards',
            'description' => __( 'Students see their own achievements. Parents see their linked student\'s last completed task.', 'mfsd-home-widgets' ),
        ],
        'rss_feed' => [
            'label'       => __( 'RSS News Feed', 'mfsd-home-widgets' ),
            'icon'        => 'dashicons-rss',
            'description' => __( 'Live headlines from any RSS or Atom feed, displayed as a rotating carousel. Role-targeted.', 'mfsd-home-widgets' ),
        ],
    ];
}

/**
 * All available MFSD roles.
 */
function mfsd_hw_roles(): array {
    return [
        'all'           => __( 'Everyone (all roles)', 'mfsd-home-widgets' ),
        'student'       => __( 'Student',              'mfsd-home-widgets' ),
        'parent'        => __( 'Parent',               'mfsd-home-widgets' ),
        'teacher'       => __( 'Teacher',              'mfsd-home-widgets' ),
        'administrator' => __( 'Administrator',        'mfsd-home-widgets' ),
    ];
}

// ─── INCLUDES ─────────────────────────────────────────────────────────────────

require_once MFSD_HW_DIR . 'includes/db.php';
require_once MFSD_HW_DIR . 'includes/admin.php';
require_once MFSD_HW_DIR . 'includes/frontend.php';

// ─── ACTIVATION / DEACTIVATION ────────────────────────────────────────────────

register_activation_hook( __FILE__, 'mfsd_hw_activate' );
function mfsd_hw_activate(): void {
    mfsd_hw_create_table();
    mfsd_hw_seed_defaults();
}