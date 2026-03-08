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
- Heart icon toggle button
- Basic `[wpef_button]` shortcode as universal fallback
- Bricks Builder element as first builder integration

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
