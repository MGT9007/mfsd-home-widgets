# MFSD Home Widgets — Technical Specification v6.0

**Plugin directory:** `mfsd-home-widgets/`
**Shortcode(s):** None (rendered via action hook `mfsd_home_widgets`)
**Version:** 6.1.0
**Status:** Current — Supersedes v6.0.0
**Author:** MisterT9007
**Purpose:** Manages and displays a role-aware widget grid on the MFSD home page. Administrators create widget instances of nine types (news cards, short videos, new courses, top scores, progress/achievements, RSS feeds, SteveGPT help bot, registration completion) and assign each instance a visibility role and sort order. The grid layout adapts automatically to widget count, with administrator-selectable named layouts for 2-, 3-, 4-, 6-, and 7-widget configurations. Students see a gamer-themed view; parents, teachers, and admins see a corporate theme.

---

## What Changed in v6.1

**v6.1.0 — New 2-, 3-, and 4-widget layout options**

Eight new named layouts added covering 2-, 3-, and 4-widget configurations. All layouts are selectable per role in the admin, consistent with the existing 6- and 7-widget layout selector pattern.

---

## Grid Layouts — Full Specification

### How layouts work

When a role has a specific number of active widgets, the admin can select a named layout for that widget count. The layout is stored per role. If no layout is selected, the grid falls back to the default auto-flow behaviour.

Layouts are implemented as CSS Grid template definitions applied via a `data-layout` attribute on the `.mfsd-hw-grid` container. Each layout name maps to a CSS class.

---

### 2-Widget Layouts

#### 2A — Wide/Narrow (75/25)
Widget 1 takes 75% of the row width, widget 2 takes 25%.

```
┌───────────────────┬─────┐
│         1         │  2  │
└───────────────────┴─────┘
```
CSS: `grid-template-columns: 3fr 1fr;`

#### 2B — Narrow/Wide (25/75)
Widget 1 takes 25% of the row width, widget 2 takes 75%.

```
┌─────┬───────────────────┐
│  1  │         2         │
└─────┴───────────────────┘
```
CSS: `grid-template-columns: 1fr 3fr;`

#### 2C — Stacked (full width, two rows)
Widget 1 full width top row, widget 2 full width bottom row.

```
┌─────────────────────────┐
│            1            │
├─────────────────────────┤
│            2            │
└─────────────────────────┘
```
CSS: `grid-template-columns: 1fr; grid-template-rows: 1fr 1fr;`

---

### 3-Widget Layouts

#### 3A — Left Hero + Right Stack
Widget 1 is full height on the left (50% width). Widgets 2 and 3 stack vertically on the right (50% width, each half height).

```
┌────────────┬────────────┐
│            │     2      │
│     1      ├────────────┤
│            │     3      │
└────────────┴────────────┘
```
CSS: `grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr;`
Widget 1: `grid-row: 1 / 3;`

#### 3B — Side by Side + Full Width Bottom
Widgets 1 and 2 side by side (equal width) in the top row, widget 3 full width in the bottom row.

```
┌────────────┬────────────┐
│     1      │     2      │
├────────────┴────────────┤
│            3            │
└─────────────────────────┘
```
CSS: `grid-template-columns: 1fr 1fr;`
Widget 3: `grid-column: 1 / 3;`

#### 3C — Full Width Top + Side by Side Bottom
Widget 1 full width in the top row, widgets 2 and 3 side by side (equal width) in the bottom row.

```
┌─────────────────────────┐
│            1            │
├────────────┬────────────┤
│     2      │     3      │
└────────────┴────────────┘
```
CSS: `grid-template-columns: 1fr 1fr;`
Widget 1: `grid-column: 1 / 3;`

---

### 4-Widget Layouts

#### 4A — Left Hero + Right Trio
Widget 1 is full height on the left (50% width). On the right (50% width): widgets 2 and 3 side by side in the top half (each 25% of total width), widget 4 full right-column width in the bottom half.

```
┌────────────┬──────┬──────┐
│            │  2   │  3   │
│     1      ├──────┴──────┤
│            │      4      │
└────────────┴─────────────┘
```
CSS: `grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 1fr 1fr;`
- Widget 1: `grid-column: 1; grid-row: 1 / 3;`
- Widget 2: `grid-column: 2; grid-row: 1;`
- Widget 3: `grid-column: 3; grid-row: 1;`
- Widget 4: `grid-column: 2 / 4; grid-row: 2;`

#### 4B — Z Pattern (diagonal)
Two equal-height rows. Row 1: widget 1 (25% width) + widget 2 (75% width). Row 2: widget 3 (75% width) + widget 4 (25% width). Creates a visual Z/diagonal pattern.

```
┌──────┬───────────────────┐
│  1   │         2         │
├──────┴──────┬────────────┤
│      3      │     4      │  ← wait, corrected below
└─────────────┴────────────┘
```

Corrected: Row 2 widget 3 is 75% width, widget 4 is 25% width:

```
┌──────┬───────────────────┐
│  1   │         2         │
├──────────────────┬───────┤
│        3         │   4   │
└──────────────────┴───────┘
```
CSS: `grid-template-columns: 1fr 3fr; grid-template-rows: 1fr 1fr;`
- Widget 1: `grid-column: 1; grid-row: 1;`
- Widget 2: `grid-column: 2; grid-row: 1;`
- Widget 3: `grid-column: 1 / 2; grid-row: 2;` — needs a 3-column grid to achieve this cleanly, see implementation note below

**Implementation note for 4B:** Use a 4-column grid to achieve the 25/75 split cleanly:
```css
grid-template-columns: 1fr 1fr 1fr 1fr;
```
- Widget 1: `grid-column: 1; grid-row: 1;`
- Widget 2: `grid-column: 2 / 5; grid-row: 1;`
- Widget 3: `grid-column: 1 / 4; grid-row: 2;`
- Widget 4: `grid-column: 4; grid-row: 2;`

---

### Admin — Layout Selector

The layout selector in the admin currently exists for 6- and 7-widget counts. Extend the same selector UI to cover 2-, 3-, and 4-widget counts.

Each layout option should be presented as a small visual thumbnail (simple CSS box diagram, as per existing pattern) with a radio button. The selected layout name is stored in `wp_options` as part of the role's layout preferences.

Layout option names to use in storage and CSS data attributes:

| Widget count | Layout slug |
|---|---|
| 2 | `2a`, `2b`, `2c` |
| 3 | `3a`, `3b`, `3c` |
| 4 | `4a`, `4b` |

---

## What Changed in v6.0

**v6.0.0 — Registration completion widget + new pre-purchase roles**

This is a major version bump driven by the registration architecture redesign (see MFSD_Registration_Architecture_v3_0.md).

### New roles added to the registry

Two new roles added to `mfsd_hw_roles()` in `mfsd-home-widgets.php`:

| Slug | Label |
|---|---|
| `prepurchaseparent` | Pre-purchase Parent (account created, registration not complete) |
| `registeredparent` | Registered Parent (registration complete, not yet purchased) |

Full updated roles registry:

```php
function mfsd_hw_roles(): array {
    return [
        'all'                => __( 'Everyone (all roles)',          'mfsd-home-widgets' ),
        'student'            => __( 'Student',                       'mfsd-home-widgets' ),
        'prepurchaseparent'  => __( 'Pre-purchase Parent',           'mfsd-home-widgets' ),
        'registeredparent'   => __( 'Registered Parent',             'mfsd-home-widgets' ),
        'parent'             => __( 'Parent',                        'mfsd-home-widgets' ),
        'teacher'            => __( 'Teacher',                       'mfsd-home-widgets' ),
        'administrator'      => __( 'Administrator',                 'mfsd-home-widgets' ),
    ];
}
```

### New widget type: registration_completion

New entry added to `mfsd_hw_widget_types()`:

```php
'registration_completion' => [
    'label'       => __( 'Registration Completion', 'mfsd-home-widgets' ),
    'icon'        => 'dashicons-clipboard',
    'description' => __( 'Multi-step form widget collecting occupation, address, child details, and optional second carer. Shown to prepurchaseparent role only. On completion, upgrades role to registeredparent.', 'mfsd-home-widgets' ),
],
```

---

## File Structure

| File | Purpose |
|------|---------|
| `mfsd-home-widgets.php` | Bootstrap: constants, widget type registry, roles registry (now includes `prepurchaseparent` and `registeredparent`), activation hook, includes |
| `includes/db.php` | Database layer — unchanged from v5.67 |
| `includes/admin.php` | Admin menu, form handlers, config sanitiser (updated for `registration_completion` type), renderers |
| `includes/frontend.php` | Frontend rendering: grid renderer, role resolver, widget dispatcher, all card renderers including new `mfsd_hw_card_registration_completion()` |
| `assets/css/admin.css` | Admin styles — unchanged from v5.67 |
| `assets/css/frontend.css` | Frontend styles — new styles for registration_completion card added |
| `assets/js/admin.js` | Admin JS — unchanged from v5.67 |
| `assets/js/carousel.js` | Carousel JS — unchanged from v5.67 |
| `assets/js/frontend.js` | Frontend JS — updated to handle registration completion form submission and step navigation |
| `assets/js/registration-completion.js` | **New file** — handles all JS for the registration completion widget: step navigation, field validation, AJAX form submission, role transition on success |

---

## Database Schema

No changes to the `wp_mfsd_hw_widgets` table schema. The `registration_completion` widget type uses the `config` JSON column like all other types.

### Config JSON Shape — registration_completion

```json
{
  "intro_heading": "Complete your registration",
  "intro_text": "Tell us a bit more about yourself and your children so we can personalise your experience.",
  "success_heading": "You're all set!",
  "success_text": "Your registration is complete. Explore My Future Self below.",
  "skip_allowed": false
}
```

| Field | Type | Purpose |
|---|---|---|
| `intro_heading` | string | Heading shown at top of widget |
| `intro_text` | string | Subtext below heading |
| `success_heading` | string | Heading shown on successful completion |
| `success_text` | string | Body text shown on successful completion |
| `skip_allowed` | bool | If true, shows a "Skip for now" option (not recommended) |

---

## Widget Type Registry (full, v6.0)

| Slug | Label | Icon |
|---|---|---|
| `news_internal` | MFS News (Internal) | dashicons-admin-post |
| `news_external` | External News / Article | dashicons-external |
| `shorts` | Shorts Video | dashicons-video-alt3 |
| `new_courses` | New Course | dashicons-welcome-learn-more |
| `top_scores` | Top Scores / Leaderboard | dashicons-chart-bar |
| `progress` | Progress & Achievements | dashicons-awards |
| `rss_feed` | RSS News Feed | dashicons-rss |
| `stevegpt_help` | SteveGPT Help Bot | dashicons-format-chat |
| `registration_completion` | Registration Completion | dashicons-clipboard |

---

## Roles Registry (full, v6.0)

| Slug | Label |
|---|---|
| `all` | Everyone (all roles) |
| `student` | Student |
| `prepurchaseparent` | Pre-purchase Parent |
| `registeredparent` | Registered Parent |
| `parent` | Parent |
| `teacher` | Teacher |
| `administrator` | Administrator |

---

## Registration Completion Widget — Detailed Spec

### Purpose

The `registration_completion` widget replaces Steps 3-7 of the old .co.uk registration form. It is displayed on the myfutureself.academy home page to users with the `prepurchaseparent` role, and is hidden from all other roles (including `registeredparent`, `parent`, `student`, `teacher`).

### Step structure (within the widget)

| Step | Content |
|---|---|
| 1 | Occupation (dropdown + "Other" free-text field) |
| 2 | Address (line 1, line 2 optional, town/city, county optional, postcode, country) |
| 3 | Child details (1-4 children — first name, age each) |
| 4 | Second parent/carer (optional — title, first name, surname, email) |

Step 4 includes Skip and Complete buttons. Skip completes registration without second carer data.

### Step logic reuse from mfsd-registration

The field structure, validation logic, and UX patterns for Steps 1-4 are lifted directly from the existing mfsd-registration plugin's Steps 4-7. Do not duplicate CSS — the registration completion widget uses its own scoped CSS that matches the academy's corporate theme (gold `#C9A84C`, dark backgrounds) rather than the .co.uk registration plugin's off-white card style.

### Frontend rendering (PHP)

New function in `includes/frontend.php`:

```php
function mfsd_hw_card_registration_completion( array $widget ): void {
    // Only render for prepurchaseparent role
    // Renders a multi-step form card within the widget grid
    // Steps handled client-side (JS) with final AJAX submission
    // On success: shows success message, page reloads after 3s
    //   (role has changed so widget will no longer appear)
}
```

### AJAX submission

The widget submits via AJAX to a new `.academy` REST endpoint:

```
POST https://myfutureself.academy/wp-json/mfsd/v1/registration/complete-profile
```

Authenticated via WordPress nonce (user is logged in). See Registration Architecture v3.0 Section 5 for full endpoint spec.

On success response `{ ok: true }`:
1. Show success message (configured `success_heading` / `success_text`)
2. After 3 seconds, reload the page
3. Reloaded page: user now has `registeredparent` role — widget is no longer visible

On error: show inline error message, keep form in current state so user can retry.

### Button states (Step 4 — second carer)

- **Default (no fields filled):** Skip button active, Complete Registration button disabled
- **Any field typed but email empty:** Skip button greyed out, Complete Registration disabled
- **Email field filled with valid email:** Complete Registration active, Skip greyed out
- **If all fields cleared:** Returns to default state

### Admin form fields (in WP Admin → Home Widgets → Add/Edit)

Four text fields for the config shape above: `intro_heading`, `intro_text`, `success_heading`, `success_text`. One checkbox for `skip_allowed`.

### Visibility / role gating

The widget should be created in the admin with roles set to `prepurchaseparent` only. The frontend renderer also programmatically enforces this — even if an admin misconfigures the roles, the card will not render for any other role.

---

## Role-Aware Theming — New Roles

Both `prepurchaseparent` and `registeredparent` should receive the **corporate gold theme** (same as `parent`, `teacher`, `administrator`) rather than the student gamer theme.

In `includes/frontend.php`, the role resolver should map these roles to the `corporate` theme:

```php
function mfsd_hw_resolve_theme( string $role ): string {
    return match( $role ) {
        'student' => 'gamer',
        default   => 'corporate',
    };
}
```

In `frontend.css`, the `body.mfsd-role-prepurchaseparent` and `body.mfsd-role-registeredparent` selectors should receive the same corporate gold overrides as `body.mfsd-role-parent`.

The WordPress theme must also add these body classes via the `body_class` filter (confirm with the theme developer if this is not already handled generically).

---

## Inter-Plugin Dependencies (updated)

| Plugin | Usage |
|--------|-------|
| `mfsd-registration` | `registration_completion` widget reuses step field logic (occupation, address, children, second carer) — logic ported/adapted, not imported directly |
| `mfsd-supabase-bridge` | `registration_completion` widget POSTs to `/wp-json/mfsd/v1/registration/complete-profile` endpoint in this plugin |
| `mfsd-parent-portal` | Progress/scores/stevegpt_help widgets — unchanged |
| `mfsd-quest-log` | Progress card — unchanged |
| `mfsd-arcade` | Top Scores / Progress cards — unchanged |
| `mfsd-ordering` | Progress card — unchanged |
| `mfsd-personality-test` | Progress card — unchanged |
| `mfsd-solution-lens` | Badge image — unchanged |
| `stevegtp` | `stevegpt_help` widget — unchanged |
| WordPress theme (`myfutureself-theme`) | Must add `body.mfsd-role-prepurchaseparent` and `body.mfsd-role-registeredparent` body classes |

---

## SteveGPT Integration

No changes from v5.67. The `stevegpt_help` widget type and its integration slots are unchanged.

A new SteveGPT integration slot for the registration completion widget may be considered in a future sprint — not in scope for v6.0.

---

## Assets

| File | Loaded | Dependencies |
|------|--------|-------------|
| `assets/css/admin.css` | Admin pages only | None |
| `assets/js/admin.js` | Admin pages only | `jquery`, `wp.media` |
| `assets/css/frontend.css` | Front end, logged-in users, front page only | `mfsd-base` |
| `assets/js/carousel.js` | Front end, logged-in users, front page only | None |
| `assets/js/frontend.js` | Front end, logged-in users, front page only | None |
| `assets/js/registration-completion.js` | Front end, logged-in users, front page only, `prepurchaseparent` role only | None |

`registration-completion.js` should only be enqueued when the current user has the `prepurchaseparent` role to avoid unnecessary loading for other users.

---

## Security

No changes to existing security model. Additional notes for `registration_completion` widget:

- The `complete-profile` REST endpoint validates the WordPress nonce on every request
- The endpoint verifies `current_user_can()` and that the current user has role `prepurchaseparent` before processing
- All submitted data is sanitised server-side — do not trust client-side validation alone
- Role upgrade (`prepurchaseparent` → `registeredparent`) happens server-side only — never from the JS layer

---

## Admin Configuration — Recommended Initial Setup

Once v6.0 is deployed, Mark should:

1. Go to WP Admin → Home Widgets → Add New
2. Select type: **Registration Completion**
3. Set label: `Registration Completion Widget`
4. Set roles: `prepurchaseparent` only
5. Set sort order: `1` (show first on the page for pre-purchase parents)
6. Configure heading/intro/success text as desired
7. Set active: on
8. Save

---

## Version History

| Version | Changes |
|---------|---------|
| 6.1.0 | New 2-, 3-, and 4-widget layout options (8 layouts total). Layout selector extended in admin to cover these widget counts. (MYF-301) |
| 6.0.0 | New widget type: `registration_completion`. New roles: `prepurchaseparent`, `registeredparent`. Corporate theme extended to both new roles. New `registration-completion.js`. New `complete-profile` REST endpoint integration. (MYF-297) |
| 5.67.6 | Fix white-on-white / black-on-black rendering: CSS overrides use hardcoded hex values with `!important`. (MYF-248) |
| 5.67.5 | Corporate gold theme CSS for `stevegpt_help` card. |
| 5.67.4 | Parent context extended: child's age and latest completed task. |
| 5.67.0 | New widget type: `stevegpt_help`. |
| 5.13.0 | RSS feed widget. Layout C variants. Per-role sort order overrides. |
| 5.x | Layout B variants. Multi-item news carousel. Full-bleed news hero. Parent linked student resolution. Badge avatar integration. |
| 3.3.0 | News cards full-bleed background image style. |
