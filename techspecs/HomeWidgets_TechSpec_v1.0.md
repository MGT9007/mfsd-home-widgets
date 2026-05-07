# MFSD Home Widgets — Technical Specification v1.0

**Plugin directory:** `mfsd-home-widgets/`
**Shortcode(s):** None (rendered via action hook `mfsd_home_widgets`)
**Version:** 5.13.0
**Author:** MisterT9007
**Purpose:** Manages and displays a role-aware widget grid on the MFSD home page. Administrators create widget instances of seven types (news cards, short videos, new courses, top scores, progress/achievements, RSS feeds) and assign each instance a visibility role and sort order. The grid layout adapts automatically to widget count, with administrator-selectable named layouts for 6- and 7-widget configurations. Students see a gamer-themed view; parents, teachers, and admins see a corporate theme.

---

## File Structure

| File | Purpose |
|------|---------|
| `mfsd-home-widgets.php` | Bootstrap: defines constants, widget type registry (`mfsd_hw_widget_types()`), roles registry (`mfsd_hw_roles()`), activation hook, includes all sub-files |
| `includes/db.php` | Database layer: table creation, seeding, CRUD functions (`mfsd_hw_get_all`, `mfsd_hw_get`, `mfsd_hw_get_for_role`, `mfsd_hw_insert`, `mfsd_hw_update`, `mfsd_hw_delete`), JSON decode helper, image URL helper |
| `includes/admin.php` | Admin menu, form handlers (`admin_post` actions for save/delete/toggle/save_layouts), config sanitiser per widget type, list/form/type-picker/layout-tab renderers, field helpers (text, textarea, image, page dropdown, news item repeater) |
| `includes/frontend.php` | Frontend rendering: grid renderer hooked to `mfsd_home_widgets`, role resolver, layout resolver, widget dispatcher, all card renderers (news, shorts, courses, scores, progress, RSS), RSS fetch with transient caching, asset enqueue |
| `assets/css/admin.css` | Admin styles: list table, type picker, add/edit form layout, field styles, multi-item article blocks |
| `assets/css/frontend.css` | Frontend styles: grid layout classes for all widget counts (1–7 with named variants), base card, card header/body/CTA, shorts video card, scores table, progress rows, news hero full-bleed card, carousel slides/arrows/dots, RSS card, role-based theme overrides |
| `assets/js/admin.js` | jQuery: WordPress Media Library picker integration, multi-item news article add/remove with live re-indexing |
| `assets/js/carousel.js` | Vanilla JS: auto-rotating carousel (5 s interval), arrow navigation, dot navigation, hover pause, swipe support for mobile |

---

## Database Schema

(Table created in `register_activation_hook` → `mfsd_hw_activate` → `mfsd_hw_create_table`)

### wp_mfsd_hw_widgets

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `type` | VARCHAR(50) | Widget type slug: `news_internal`, `news_external`, `shorts`, `new_courses`, `top_scores`, `progress`, `rss_feed` |
| `label` | VARCHAR(200) | Admin-facing label, not shown on front end |
| `roles` | VARCHAR(500) | JSON array of role slugs, e.g. `["student"]` or `["all"]` |
| `active` | TINYINT(1) | 1 = live, 0 = hidden |
| `sort_order` | INT | Global default grid position (lower = earlier) |
| `role_sort_orders` | VARCHAR(500) | JSON object of per-role overrides, e.g. `{"student":2,"parent":4}` |
| `config` | LONGTEXT | JSON: type-specific content fields (see Config Shapes below) |
| `created_at` | DATETIME | Auto-set on insert |
| `updated_at` | DATETIME | Auto-updated on change |

**Indexes:** `type`, `sort_order`

**Live migration:** On `mfsd_hw_create_table()`, an `ALTER TABLE` adds `role_sort_orders` to pre-existing installs that lack the column.

**Seed data:** On fresh activation, three default instances are inserted if the table is empty: MFS News (news_internal, all roles), Top Scores (top_scores, all roles), Progress & Achievements (progress, all roles).

### Config JSON Shapes by Type

| Type | Config fields |
|------|--------------|
| `news_internal` / `news_external` | `items[]`: array of up to 10 objects, each with `headline`, `summary`, `image_id`, `link`, `cta_text`. Backwards-compatible with old flat single-item format. |
| `new_courses` | `headline`, `summary`, `image_id`, `link`, `cta_text` |
| `shorts` | `title`, `video_url`, `image_id`, `duration`, `cta_text` |
| `top_scores` | `games` (all / asteroids / mario / hgc), `score_count` (1/3/5/10), `mode` (global / student) |
| `progress` | `show_badge` (bool), `show_score` (bool), `show_task` (bool) |
| `rss_feed` | `feed_url`, `feed_limit` (1–20), `feed_prefix`, `badge_label`, `cta_text`, `link_out` (bool) |

---

## Key Flows

### Front End Rendering

1. The theme calls `do_action('mfsd_home_widgets')` on the home page.
2. `mfsd_hw_render_grid()` (hooked to `mfsd_home_widgets`) checks `is_user_logged_in()`; if not logged in, returns silently.
3. The current user's role is resolved via `mfsd_get_user_role()` (from MFSD theme/parent portal) or a local fallback that inspects `wp_get_current_user()->roles`.
4. `mfsd_hw_get_for_role($role)` fetches all active widgets visible to that role (those with matching role or `"all"` in their roles array), sorted by per-role sort order (falling back to global `sort_order`).
5. Widget count and the stored layout setting for the role (from `mfsd_hw_role_layouts` option) determine the CSS modifier class on the grid wrapper (`mfsd-hw-grid--7`, `mfsd-hw-grid--7b`, `mfsd-hw-grid--7c`, `mfsd-hw-grid--6`, etc.). Counts other than 6 and 7 use fully automatic layouts.
6. Each widget is rendered inside a `mfsd-hw-grid__cell` div, delegating to the appropriate card function.

### News / RSS Carousel

1. If a news or RSS widget has 2+ items/headlines, the wrapper receives the class `mfsd-hw-carousel`.
2. `carousel.js` finds all `.mfsd-hw-carousel` elements and sets up a 5-second auto-rotation timer per carousel.
3. Arrow buttons (prev/next) and dot buttons allow manual navigation; hovering pauses auto-rotation; swipe left/right is supported on touch devices.
4. Single-item widgets have no carousel class and no controls — slides always render as `position:relative`.

### RSS Feed Fetch

1. `mfsd_hw_card_rss()` calls `mfsd_hw_fetch_rss($feed_url, $limit, $prefix)`.
2. The function checks a WordPress transient keyed on `md5($feed_url . $limit)`. On a cache hit the array is returned immediately.
3. On a miss, `wp_remote_get()` fetches the feed (15 s timeout, 5 redirects, custom User-Agent).
4. The response body is parsed with `simplexml_load_string()`. Both RSS 2.0 (`channel/item`) and Atom (`entry`) structures are supported.
5. Image URLs are extracted from `<enclosure>`, `media:thumbnail`, and `media:content` namespace elements.
6. Results are cached for 30 minutes; on error the transient is set to an empty array with a 5-minute TTL so failed fetches do not hammer the remote server.

### Progress Card — Parent View

1. `mfsd_hw_card_progress()` receives the user's role string.
2. If the role is `parent` or `teacher`, `mfsd_hw_get_linked_student_id()` queries `wp_mfsd_parent_student_links` (from the Parent Portal plugin) for an active link ordered by `is_primary_contact DESC`.
3. The linked student's latest badge is read from `wp_mfsd_badges`, latest completed task from `wp_mfsd_task_progress`, and top score from `wp_mfsd_arcade_scores`.
4. Task slugs are resolved to page URLs via a hardcoded map (`mfsd_hw_task_url_map()`); badge slugs are resolved to image paths via `mfsd_hw_badge_image_url()`.
5. For the "Who Am I" badge, the student's MBTI type is read from `wp_mfsd_ptest_results` and mapped to an avatar filename.

### Progress Card — Student View (Not Started)

1. If a student has no badges, tasks, or scores, the card checks `wp_mfsd_enrolments` for an active course enrolment.
2. If enrolled, the first task in the course (by `sequence_order`) is read from `wp_mfsd_task_order`.
3. The CTA becomes "Start My Course" linking to the course details page, and the empty-state message names the first task and its link.

---

## AJAX / REST Endpoints

This plugin does not register any REST or AJAX endpoints. All data is rendered server-side via PHP.

---

## Admin Panel

**Location:** WP Admin → MFSD Widgets (menu position 57, dashicons-grid-view)

The page has two tabs:

### Widgets Tab (default)

Lists all widget instances in a table with columns: ID, Label (with type icon), Type, Visible to (role pills), Role Order (per-role effective positions with gold highlight for custom overrides), Status (Live / Off badge), and Actions (Edit, Pause/Activate, Delete).

- **+ Add Widget** leads to a type picker card grid (3 columns, 7 types), then to the add/edit form.
- **Edit** opens the form pre-populated.
- **Pause/Activate** toggles `active` via `admin_post_mfsd_hw_toggle` (nonce-protected).
- **Delete** triggers `admin_post_mfsd_hw_delete` (nonce-protected, JS confirmation).

**Add/Edit form layout:** two-column — left panel (Widget Identity label + Content fields) and right sidebar (Publish checkbox, Grid Position per-role number inputs + global default, Visible to role checkboxes). The form POSTs to `admin_post_mfsd_hw_save`.

**Content fields vary by type:**
- News types: repeatable article blocks (up to 10), each with headline, summary, image picker, link (page dropdown for internal, URL input for external), CTA text. JS handles adding/removing articles with live index re-numbering.
- Other types: flat form fields rendered by helper functions.

### Grid Layouts Tab

Allows setting a named layout per role when that role has exactly 6 or 7 active widgets. Three named layouts are available for each count (Layout A/B/C), shown as interactive mini SVG-style grid thumbnails built in PHP. Roles with other widget counts see an auto-layout preview (non-selectable). A right-side panel shows the current widget order per role with tab navigation. Settings saved via `admin_post_mfsd_hw_save_layouts`.

**Capability required:** `manage_options` for all write operations.

---

## SteveGPT Integration

This plugin does not make AI calls. It reads data from tables created by other plugins (Quest Log badges, Arcade scores, Ordering task progress) but performs no AI inference itself.

---

## Assets

| File | Loaded | Dependencies |
|------|--------|-------------|
| `assets/css/admin.css` | Admin pages only (`admin_enqueue_scripts`) | None |
| `assets/js/admin.js` | Admin pages only | `jquery`, `wp.media` (via `wp_enqueue_media()`) |
| `assets/css/frontend.css` | Front end, logged-in users, front page only | `mfsd-base` (theme base styles) |
| `assets/js/carousel.js` | Front end, logged-in users, front page only | None (vanilla JS) |

Front-end assets are enqueued only when `is_user_logged_in() && is_front_page()`.

---

## Security

- All `admin_post_*` handlers call `check_admin_referer()` with action-specific nonce keys.
- All write handlers verify `current_user_can('manage_options')` before executing.
- All config values are sanitised in `mfsd_hw_sanitize_config()` using `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw`, `sanitize_key`, and integer casting.
- Role slugs submitted from forms are validated against the `mfsd_hw_roles()` registry.
- Widget type submitted from forms is validated against the `mfsd_hw_widget_types()` registry.
- All database queries use `$wpdb->prepare()`.
- All output on the front end and admin uses `esc_html()`, `esc_attr()`, `esc_url()` as appropriate.
- Front-end grid rendering requires `is_user_logged_in()`.

---

## Inter-Plugin Dependencies

| Plugin | Usage |
|--------|-------|
| `mfsd-parent-portal` | `mfsd_hw_get_linked_student_id()` queries `wp_mfsd_parent_student_links` to resolve which student a parent sees in the progress and scores widgets |
| `mfsd-quest-log` | Progress card reads `wp_mfsd_badges` for the latest badge; badge images read from `mfsd-quest-log/assets/images/badges/` and `mfsd-quest-log/assets/images/characters/` |
| `mfsd-arcade` | Top Scores and Progress cards read `wp_mfsd_arcade_scores` |
| `mfsd-ordering` | Progress card reads `wp_mfsd_task_progress` (completed tasks), `wp_mfsd_enrolments`, `wp_mfsd_courses`, and `wp_mfsd_task_order` for the "not started" enrolment state |
| `mfsd-personality-test` | Progress card reads `wp_mfsd_ptest_results` for MBTI type to resolve personality avatar; avatar images in `mfsd-personality-test/assets/Avatars/` |
| `mfsd-solution-lens` | Badge image for `badge_solution_lens` is read from `mfsd-solution-lens/images/badge_solution_lens.png` |
| WordPress theme (`myfutureself-theme`) | Calls `do_action('mfsd_home_widgets')` to embed the grid; provides `mfsd_get_user_role()` helper; provides `mfsd-base` CSS dependency and CSS custom properties consumed by `frontend.css` |

---

## Version History

| Version | Changes |
|---------|---------|
| 5.13.0 | Current version. RSS feed widget type. Layout C (7c, 6c) named grid variants. Per-role sort order overrides (role_sort_orders column). Live column migration for existing installs. |
| 5.x (earlier) | Layout B (7b, 6b) variants. Multi-item news carousel with up to 10 articles. Full-bleed news hero card style. Progress card "not started" enrolment state with first task deep-link. Parent view linked student resolution. Badge avatar integration for MBTI types. |
| 3.3.0 | (frontend.php comment) News cards full-bleed background image style introduced. |
