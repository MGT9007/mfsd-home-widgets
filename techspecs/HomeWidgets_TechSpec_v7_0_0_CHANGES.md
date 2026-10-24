# MFSD Home Widgets: Change Specification (Commitment Cards widget, safeguarding)

**Plugin:** `mfsd-home-widgets` **6.4.0 → 7.0.0** (built 24 Oct 2026). A new widget type is a significant new capability, so a major bump under the CLAUDE.md rules; earlier drafts said 6.2.0 and 6.5.0.
**Applies on top of:** `mfsd-home-widgets/techspecs/HomeWidgets_TechSpec_v6_0.md`
**Suggested repo path:** `mfsd-home-widgets/techspecs/HomeWidgets_TechSpec_v7_0_0_CHANGES.md`
**Status:** Built as 7.0.0 (24 Oct 2026). Checked against the code 23 Oct 2026 (this `_checked` copy).
**Depends on:** `mfsd-commitments` v1.0.0 (Commitments spec `_checked`: REST §9, check-in modal §9.1, per-parent visibility filter §8.2a), `mfsd-safeguarding` v1.0.0, `stevegtp` 13.9.0 (SteveGPT spec v8.11.0)
**Linear:** ticket B

> **Checked against (23 Oct 2026):** `mfsd-home-widgets` v6.4.0, commit `ddfff15` (1 Sep 2026), local clone clean.
> - Widget types are registered in `mfsd_hw_widget_types()` (`mfsd-home-widgets.php`) and rendered by the switch in `mfsd_hw_render_widget()` (`includes/frontend.php`).
> - All cards live in `includes/frontend.php`. There are no per-card files, and the only scripts are `assets/js/carousel.js`, `registration-completion.js` and `admin.js`.
> - `carousel.js` auto-rotates **every** `.mfsd-hw-carousel` with 2 or more slides, every 5 s, and there's **no opt-out** today.
> - `fitGrid()` sets an explicit grid height.
> - Linked children come from `mfsd_hw_get_linked_student_id()`, which returns **one** child.
> - No script uses `localStorage` yet.
>
> **Decisions applied (Mark, 23 Oct 2026):** commitments are required in every task; there are no card limits; a parent only sees a task's card once they've reached that task's final summary (Dream & Junk Jobs guessing game, Wider Interests prediction); moderation items are hidden from parents until released; Ask Steve chats are safeguarded inside SteveGPT, with the demo and parents excluded.

---

## 1. New widget type: `commitment_cards`

### 1.1 Registration
- Add to `mfsd_hw_widget_types()`: label **"Commitment Cards"**, icon `dashicons-yes-alt`, description "Students' commitment cards; parents confirm them".
- Add `case 'commitment_cards': mfsd_hw_card_commitments( $config, $role ); break;` to `mfsd_hw_render_widget()`.
- New files: `includes/card-commitments.php` (required from `mfsd-home-widgets.php`), `assets/js/commitments-widget.js` and `assets/css/commitments-widget.css`. Enqueue them only when the widget is in the current role's grid, with the `?m=filemtime` cache-buster.

### 1.2 Admin config

| Field | Default | Notes |
|---|---|---|
| Title | "My commitments" (student) / "Their commitments" (parent) | |
| Show Done tab | On | |
| Show to roles | `student`, `parent` | `registeredparent`: only if they have an active link in `wp_mfsd_parent_student_links`, otherwise render nothing. Never `prepurchaseparent`. `admin` sees a preview with sample cards |

If `mfsd-commitments` isn't active, the admin shows a notice and the widget renders nothing on the front end.

### 1.3 Rendering
- **Server side:** only the shell. That's the title, the tabs, an empty card track and a skeleton card. The home page may be cached, so no per-user data goes into the HTML.
- **Client side:** fetch a fresh nonce first (the `registration-earlybird.js` pattern), then:
  - **Student:** `GET /mfsd/v1/commitments`
  - **Parent:**
    1. `GET /mfsd/v1/commitments/children` for the linked children (multi-child; Home Widgets' own `mfsd_hw_get_linked_student_id()` only returns one, so don't use it here)
    2. `GET /mfsd/v1/commitments/child/{child_id}` for each child
- **Card states, sort order, Done tab, empty state, celebration and parent prompts:** as in Commitments spec §12.1–12.2.
- **"I did it" / "Update"** opens the **shared check-in modal from `mfsd-commitments`**, not a copy.
- **No card limits:** no "keep" screen, no limit messages, and the widget never refuses a card.

### 1.4 What a parent may see (new)
The widget shows only what the Commitments REST routes return. The rules below are enforced **server-side in `mfsd-commitments`**; the widget just mustn't work around them, for example by caching or reading cards some other way.

1. **Cards held back until the parent reaches the final summary.** `mfsd_commitment_visible_to_parent` (Commitments §8.2a) hides, for example:
   - a Dream & Junk Jobs card until **that** parent has finished the guessing game
   - a Wider Interests Spark until **that** parent has made their prediction

   Hidden cards don't appear in the carousel or in the "waiting for you" count.
2. **Teaser card (new, default on):** for each hidden card, the route returns a placeholder:
   ```json
   { "teaser": true, "source_task": "junk_jobs", "label": "Dream & Junk Jobs", "url": "/my-future-self-foundation-course/week-2/junk-jobs/?student_id=123" }
   ```
   - The widget shows: "**{Child} has made a commitment in Dream & Junk Jobs.** Play the guessing game to see it →".
   - It has no title, no detail and no button other than the link.
   - Admin setting: "Show teaser cards" (default on).
3. **Moderation items are withheld.**
   - If a card's title, detail or check-in text raised a **moderation** flag that hasn't been released (`mfsd_safeguarding_is_withheld( $child_id, 'commitments', $commitment_id )`, a new helper in the safeguarding plugin), the parent route **leaves that card out entirely**. Also no teaser, because a teaser would hint something is there.
   - The child still sees their own card.
   - Once an admin releases the flag, the card appears.
4. **Concern and urgent items aren't hidden.** The card shows as normal. The parent learns about the concern through the task's parent summary, not the widget. The widget never shows flag labels.

### 1.5 Carousel behaviour
- Reuse the `.mfsd-hw-carousel` markup classes so the styling matches.
- **Auto-rotation must be off for this widget.**
  - **Code change in `carousel.js`:** read `data-autoplay` on the carousel element. When it's `"0"`, never call `startAuto()`, including after arrow and dot clicks, which call `startAuto()` today.
  - The default stays as it is (auto) for the news and RSS carousels.
- Swipe on touch, arrows on desktop and the left/right keys. Dots for up to 8 cards, and a "3 / 11" counter above that.
- **Initialise after the REST data arrives:** call a new exported `window.mfsdHwInitCarousel( el )`, rather than relying on the `DOMContentLoaded` scan, which runs before the cards exist.

### 1.6 Fitting the grid
`fitGrid()` sets an explicit grid height, and the layout decides each cell's height. The widget must:
- have a minimum content height of about 260px. Below that, the card body scrolls inside the cell (`overflow-y: auto`) rather than pushing the grid.
- never set its own height on the grid or the cell
- call the existing `fitGrid()` once after the cards render (expose it as `window.mfsdHwFitGrid`), because it ran before the content existed
- be checked in every layout that can hold it, especially **7b** and **4a**, where the Ask Steve card was squeezed before

**Suggested default placement:** position 1 or 2 for `student` and `parent`.

### 1.7 Themes
- Students see the gamer theme and parents the corporate theme, as with the other widgets.
- Source colours come from each card's registered style (`mfsd_commitment_sources`).
- Text keeps at least 4.5:1 contrast on both themes.

### 1.8 Multi-child parents
- One tab per child (first name) above the carousel, each with its waiting count (visible cards only).
- The last tab chosen is remembered in `localStorage`, inside try/catch. This is the first use of `localStorage` in the plugin. Without it, the first child is shown.

## 2. Ask Steve widget (`stevegpt_help`): safeguarding

Chat safeguarding happens **inside SteveGPT** (spec v8.11.0). Home Widgets only needs to label its two slots, and fix one role bug found while checking.

1. **Slot labels.** In the `stevegpt_plugin_integration_slots` filter (`mfsd-home-widgets.php`):
   - **Student help bot:** no `task_slug`, so flags are recorded as `steve_chat`.
   - **Parent help bot:** add `'audience' => 'parent'`, so SteveGPT adds the PARENT_CHAT block. Parents' messages aren't keyword-checked.
2. **Role bug (checked):** `mfsd_hw_card_stevegpt()` decides parent vs student with `in_array( $role, ['parent','teacher'] )`. So a **`registeredparent` or `prepurchaseparent` gets the *student* help bot**, with a context string that calls them "Student name: {parent's name}". `mfsd_hw_card_progress()` uses the same test.
   - **Fix:** treat `parent`, `registeredparent` and `prepurchaseparent` as parents in both functions, through a new helper `mfsd_hw_is_parent_role( $role )`.
   - **Safeguarding effect:** SteveGPT only checks users with the WP role `student`, so these parents were never wrongly flagged. But they were getting the wrong bot and instructions.
3. **Content aware stays off** on both help chatbots, as the slot comments already say. With it on, SteveGPT adds the MBTI code itself.
4. **Context strings:** the parent context includes the child's name, age and latest task, all of which a parent may see. It never includes flags or answers. No change needed.

## 3. Other widgets
- **`progress`:** an optional "Commitments: {done} done" line when `mfsd-commitments` is active and it fits the layout. It uses the same parent-role helper as §2.2.
- **`registration_completion`, news, RSS, shorts, courses, top scores:** no change.
- **Course versioning (later):** `mfsd_hw_task_url_map()` and `mfsd_hw_task_display_name()` in the progress widget should use the student's course version once versioning ships (Course Versioning spec §8). Not needed for 6.5.0.

## 4. Testing
1. **Grid:** add the widget for `student` and `parent`. It renders in every layout that has room (check 7b and 4a especially), and the other cells' heights don't change.
2. **Student:**
   - no cards → empty state
   - 3 cards → carousel with dots, **no auto-rotation** (wait 15 s)
   - the news carousel still auto-rotates
3. **Parent with two linked children:** two tabs with correct counts. Validating from the widget updates the count without a reload. The chosen tab is remembered.
4. **Held-back card:**
   - the child completes Dream & Junk Jobs (card created)
   - the parent who hasn't played sees the teaser only, linking to the game, and the count excludes it
   - after playing, the real card appears
   - a second parent still sees the teaser until they play
5. **Moderation:** a commitment whose text raised an unreleased moderation flag doesn't appear for the parent at all (no teaser), but appears for the child. After release, it appears for the parent.
6. **Cached HTML:** the cached home page contains no card text or teaser text (view source).
7. **Commitments inactive:** `mfsd-commitments` deactivated → the widget renders nothing, and the admin notice shows.
8. **Ask Steve:**
   - a `registeredparent` gets the **parent** help bot with parent context
   - a student's urgent word in Ask Steve raises a `steve_chat` flag and gets a safety reply (SteveGPT v8.11.0)
   - a parent's message raises no flag
9. **Keyboard and layout:** all buttons reachable, left and right keys work, focus visible. Check 360px, 768px and desktop, in light and dark themes.

## 5. Open decisions

| # | Decision | Default |
|---|---|---|
| 1 | Teaser card for held-back cards | On |
| 2 | Show the widget to `registeredparent` with an active link | Yes |

## 6. As built (7.0.0, 24 Oct 2026)

- Files: `includes/card-commitments.php` (shell, role/mode rules, asset loading), `assets/js/commitments-widget.js`, `assets/css/commitments-widget.css`; `carousel.js` gains `data-autoplay="0"`, `window.mfsdHwInitCarousel()`, `window.mfsdHwFitGrid()`, left/right keys and a "3 / 11" counter.
- Modes: `student`; `parent` (roles `parent` and `registeredparent`, only with an active child link); `preview` for admins (sample cards, no API calls). `prepurchaseparent` and `teacher` see nothing.
- All requests go through `window.MFSDCommitments.api()` from `mfsd-commitments` (fresh nonce, one retry on a stale nonce).
- Parent cards: "I saw it / Let's talk" opens the shared validate modal. Teaser link: "Play the guessing game to see it" for Dream & Junk Jobs, "Open {task} to see it" otherwise; `student_id` is appended when the producer's URL has none.
- The Done tab button has `id="mfsd-commitments-done"`, which Quest Log 3.0.0's Follow Through shelf links to (`/#mfsd-commitments-done` opens the Done tab).
- `mfsd_hw_is_parent_role()` covers `parent`, `registeredparent`, `prepurchaseparent` and `teacher` (teacher kept its previous parent-style view). Used by Ask Steve and Progress.
- Parent help bot slot has `'audience' => 'parent'`. The student slot has no `task_slug`, so its flags are recorded as `steve_chat`.
- **Not built:** §3's optional "Commitments: {done} done" line in the Progress widget (the progress card markup is dense; the Done tab already shows the count).
