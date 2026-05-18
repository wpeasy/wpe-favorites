# WordPress Plugin Configuration

## Plugin Header

```php
/**
 * Plugin Name: WPE Favorites
 * Plugin URI:
 * Description:
 * Version: 1.0.0
 * Author: Alan Blair
 * Author URI:
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpef
 * Requires at least: 6.5
 * Requires PHP: 8.0
 */
```

## Constants & Prefixes

| Item | Value |
|------|-------|
| **Namespace** | `WPE\Favorites` |
| **Main Plugin File** | `wpe-favorites.php` |
| **Plugin Slug** | `wpe-favorites` |
| **Constants Prefix** | `WPEF_` |
| **REST API Namespace** | `wpef/v1` |
| **Database Table Prefix** | `wpef_` |
| **JS Global** | `WPEF` (window.WPEF) |
| **CSS Prefix** | `.wpef-` |
| **Text Domain** | `wpef` |
| **Admin Menu Slug** | `wpef-settings` |
| **Minimum WordPress** | 6.5 |
| **Minimum PHP** | 8.0 |

## Script Loading

Always enqueue admin JavaScript with `wp_enqueue_script_module()` (available since WP 6.5). ES modules are automatically scoped — variables cannot leak into or collide with the global scope (e.g. WordPress's Underscore.js `_` global).

- **Admin JS** — use `wp_enqueue_script_module()`. Since `wp_add_inline_script()` does not work with script modules, output any config data as an inline `<script>` in the page render method instead. The inline script executes before the deferred module.
- **Frontend JS** — use `wp_enqueue_script()` with Vite's `lib` mode (`formats: ['iife']`). The IIFE wrapper scopes all variables.

Do **not** use `wp_enqueue_script()` for admin Svelte/Vite builds — the default Vite output uses top-level `var` declarations that pollute the global scope and break on hosts where other plugins load conflicting globals.
