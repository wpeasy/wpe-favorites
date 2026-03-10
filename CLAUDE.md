# CLAUDE.md

## Project Purpose

WPE Favorites adds a user favorites system to WordPress. Users can favorite any Post or Custom Post Type from the frontend. Favorites persist in localStorage for instant, anonymous access and sync to the user's WordPress profile when logged in. The plugin is modular — designed for easy addition of new features and page builder integrations (starting with Bricks Builder).

## Requirements

### Core Data Model
- Each favorite stores **Post ID** and **Post Type** (e.g., `{ postId: 42, postType: 'product' }`)
- Favorites are stored as a flat array, filterable by post type
- Server-side storage: WordPress **user meta** (key: `wpef_favorites`)
- Client-side storage: **localStorage** (key: `wpef_favorites`)

### REST API
- `GET /wpef/v1/favorites` — retrieve current user's favorites
- `POST /wpef/v1/favorites` — add a favorite (body: `{ postId, postType }`)
- `DELETE /wpef/v1/favorites/{postId}` — remove a favorite
- `PUT /wpef/v1/favorites` — bulk sync (body: full favorites array, used for login merge)
- All endpoints require authentication (`current_user_can('read')`)
- Return the full updated favorites array on every mutation

### Frontend Behavior
- Toggle favorite on/off from any post via a button/icon
- Instant UI response — update localStorage first, then sync to server
- Works for anonymous users (localStorage only, no REST calls)
- On login sync: if localStorage favorites exist and the user just logged in, merge with their server-side favorites and clear the local-only entries

### Login Sync Strategy
- On authenticated page load, compare localStorage favorites with server response
- Merge: union of both sets (no duplicates, matched by `postId`)
- After merge, push the combined set to the server and update localStorage
- Only runs once per session (flag in sessionStorage to avoid repeat syncs)

### Supported Post Types
- All public post types enabled by default
- Filterable via `wpef_supported_post_types` hook

### Post Deletion Cleanup
- Hook into `before_delete_post` to remove deleted posts from all users' favorites

### JS Events
- Emit custom events on `document` for third-party/builder integration:
  - `wpef:added` — favorite added (detail: `{ postId, postType }`)
  - `wpef:removed` — favorite removed (detail: `{ postId }`)
  - `wpef:synced` — login merge completed (detail: `{ favorites }`)

### Frontend Output
- Heart icon toggle button (default, zero-config)
- `[wpef_button]` shortcode with optional labels and custom icons
- Bricks Builder element as first builder integration

### Button Labels & Custom Icons
- **Default:** icon-only heart, identical to original markup
- **With labels:** dual-state containers shown/hidden via CSS (no JS changes)
- **Shortcode attributes:** `label`, `active_label`, `icon_class`, `active_icon_class`
- **Bricks element:** uses native `'type' => 'icon'` control (font icons + SVG), rendered via `Element::render_icon()` and passed as `icon_html`/`active_icon_html`
- **Accessibility:** `aria-label` only on icon-only buttons; when labels are present, the visible text serves as the accessible name. `aria-pressed` toggled on all buttons.
- **CSS architecture:** structural CSS (layout, visibility) outside any layer; styling CSS (colors, opacity, icons) in `@layer wpef` for easy overriding

### Shortcode Reference

`[wpef_button]` — renders the favorite toggle button.

| Attribute | Default | Description |
|-----------|---------|-------------|
| `post_id` | Current post | Post ID to favorite |
| `label` | *(empty)* | Inactive state label text |
| `active_label` | *(empty)* | Active state label text (falls back to `label`) |
| `icon_class` | *(empty)* | CSS icon class for inactive state (e.g. `fa-regular fa-heart`) |
| `active_icon_class` | *(empty)* | CSS icon class for active state (e.g. `fa-solid fa-heart`) |

Programmatic-only params (not available in shortcode attributes):
- `icon_html` / `active_icon_html` — pre-rendered icon HTML (used by Bricks integration)

Filter: `wpef_button_atts` — modify shortcode attributes before rendering.

### Bricks Builder Integration

**Elements:**
- `Element_Favorite_Button` — favorite toggle with grouped controls (Inactive, Active, Hover) for labels, icons, and typography
- `Element_User_Count` — displays current user's favorite count with post type filtering (multi-select or dynamic data)

**Custom Queries:**
- `wpef_favorites` — loops through current user's favorited posts (with post type filter supporting dynamic data via Source toggle)
- `wpef_post_types` — loops through favorites-enabled post types (multi-select filter, returns array data for `{query_array}` tags)

See **BRICKS_NOTES.md** for full control and query documentation.

### Bricks Dynamic Data & Conditions
- **Dynamic data tags** registered via `bricks/dynamic_tags_list`, rendered via `bricks/dynamic_data/render_tag` and `render_content`
- **Conditions** registered via `bricks/conditions/groups`, `bricks/conditions/options`, evaluated via `bricks/conditions/result`
- All Bricks dynamic data logic lives in `src/Integrations/Bricks/DynamicData.php`
- `{wpef_user_count}` defaults to current post type; use `:all` for total, or `:slug` for specific type
- `{wpef_post_count}` defaults to current post; use `:post_id` for specific post
- `{wpef_global_count}` defaults to all types; use `:post_type` for specific type

### Modularity
- **PHP:** Feature modules register via a central registry (e.g., `Plugin::register_module()`)
- **JS:** Each feature is a separate ES module, loaded only when needed
- Builder integrations (Bricks, Elementor, etc.) are isolated modules that hook into the core favorites API
- Core favorites logic has zero builder dependencies

## Required Reading

Read these files BEFORE writing code:

| File | Purpose |
|------|---------|
| **WORDPRESS.md** | Plugin header, constants, prefixes, and WordPress configuration |
| **CODE_STANDARDS.md** | Naming conventions, security, PHP/JS/CSS standards |
| **BRICKS_NOTES.md** | Bricks Builder integration, Vue API, iframe patterns |
