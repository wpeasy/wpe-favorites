# Changelog

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
