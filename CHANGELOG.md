# Changelog

## 1.0.6 — 2026-03-11

### Added

- **Max favorites limits** — configurable per-post-type and global total limits from the settings page
- **Settings table UI** — post type checkboxes now displayed in a two-column table with per-type "Max per User" number inputs
- **Server-side enforcement** — `add()` returns a `WP_Error` when per-type or global limits are exceeded; `sync()` silently truncates to configured limits
- **Client-side pre-flight check** — limits passed via `window.WPEF.limits` for instant feedback before server round-trip
- **Settings getters** — `Settings::get_limit_for_type()` and `Settings::get_max_favorites()` for use by other modules

---

## 1.0.5 — 2026-03-10

### Added

- **Multi-user localStorage** — each logged-in user gets their own localStorage key (`wpef_{userId}`), preventing data contamination on shared devices
- **Sync prompt** — when anonymous favorites exist and a different returning user logs in, a non-blocking banner asks whether to merge or discard them
- **Server refresh on every page load** — logged-in users always fetch the latest favorites from the server, so changes made in other windows/devices appear on reload

### Changed

- **Login sync split** — the full merge decision tree (anon key handling, conflict prompt) only runs once per session; subsequent reloads do a lightweight server refresh
- **Legacy migration** — old `wpef_favorites` localStorage key is automatically migrated to the new per-user key on first load

---

## 1.0.4 — 2026-03-10

### Added

- **Bricks dynamic data tags** — `{wpef_user_count}`, `{wpef_post_count}`, `{wpef_global_count}` with optional `:post_type` / `:post_id` parameters; `{wpef_user_count}` defaults to current post type, use `:all` for total
- **Bricks conditions** — "Post Is Favorited" and "User Has Favorites" for element visibility control

### Fixed

- **Documentation element names** — now match Bricks element labels (User Favorite Count, Post Favorite Count, Global Favorite Count)

---

## 1.0.3 — 2026-03-09

### Improved

- **Favorite button aria-labels** — dynamic labels based on post title and state ("Add {title} to favorites" / "Remove {title} from favorites")
- **`aria-pressed` in server HTML** — favorite buttons render with `aria-pressed="false"` before JS loads
- **Clear button keyboard support** — Escape key cancels the confirmation state
- **Favorites loop list** — self-closing `[wpef_favorites]` now has `aria-label="Favorite posts"` for screen readers

### Added

- **Documentation tab** — JavaScript API & REST reference moved to its own "JavaScript & REST" tab

---

## 1.0.2 — 2026-03-09

### Added

- **Global Count** — Post Type Source toggle (Select multi-select / Dynamic) matching User Count pattern
- **Post Count** — Post ID Source toggle (Select / Dynamic) for dynamic post ID resolution
- **Updater changelog** — GitHub release notes rendered as formatted HTML in the WordPress update details popup
- **Updater description** — notes that full documentation is available in the admin Documentation submenu

---

## 1.0.1 — 2026-03-09

### Added

- **GitHub auto-updater** — checks for new releases on the public GitHub repo and offers one-click updates from the WordPress Plugins page

---

## 1.0.0 — 2026-03-09

Initial release.

### Features

- **Favorite Button** — toggle button with heart icon, optional labels and custom icons (dual-state CSS show/hide)
- **Shortcodes** — `[wpef_button]`, `[wpef_clear]`, `[wpef_user_count]`, `[wpef_post_count]`, `[wpef_global_count]`, `[wpef_favorites]` loop with `[wpef_field]`, `[wpef_post_types]`
- **Clear Favorites** — shortcode and Bricks element with optional post type filtering and double opt-in confirmation
- **localStorage + REST sync** — instant anonymous favorites with server sync on login
- **Login merge** — union of local and server favorites on authenticated page load
- **Post deletion cleanup** — auto-removes deleted posts from all users' favorites
- **Bricks Builder integration**
  - Favorite Button element with Inactive/Active/Hover control groups, native icon picker, typography controls
  - Clear Favorites element with confirmation text and post type filtering
  - User Count, Post Count, and Global Count elements
  - User Favorites query loop with post type source (Select/Dynamic), pagination
  - Favorite Post Types query loop (array loop with multi-select filter)
- **REST API** — GET, POST, PUT, DELETE endpoints for favorites CRUD, clear, and public counts
- **JS events** — `wpef:added`, `wpef:removed`, `wpef:synced`, `wpef:cleared`
- **Admin documentation** — Svelte-powered docs with Shortcodes and Bricks tabs
