# Licensing Implementation - FluentCart Integration

**Reference:** https://github.com/WPManageNinja/fluent-plugin-updater-example

Implement licensing using the FluentCart License activation and automatic updater system.

---

## Plugin Configuration

| Item | Value |
|------|-------|
| **Plugin Name** | WPE Favorites |
| **Namespace** | `WPE\Favorites` |
| **Constants Prefix** | `WPEF_` |
| **Text Domain** | `wpef` |
| **Main Plugin File** | `wpe-favorites.php` |
| **Admin Menu Slug** | `wpef-settings` |
| **License Constant Prefix** | `WPEF_LICENSE_` |
| **License Settings Key** | `wpef_license_settings` |
| **License Slug** | `wpe-favorites` |
| **Licensing Namespace** | `WPE\Favorites\Licensing` |

---

## Configuration Parameters

**IMPORTANT:** All licensing/updater settings must be defined as global constants in the main plugin file. This centralizes configuration and makes it easy to update values in one location.

### Required Constants (Define in main plugin file)

```php
// Licensing Configuration Constants
define('WPEF_LICENSE_ITEM_ID', 1016);
define('WPEF_LICENSE_API_URL', 'https://alanblair.co/');
define('WPEF_LICENSE_SLUG', 'wpe-favorites');
define('WPEF_LICENSE_SETTINGS_KEY', 'wpef_license_settings');
```

### Optional Constants (Define in main plugin file)

```php
// Optional Licensing Settings
define('WPEF_LICENSE_MENU_TYPE', 'submenu');
define('WPEF_LICENSE_MENU_TITLE', 'License');
define('WPEF_LICENSE_PAGE_TITLE', 'WPE Favorites - License');
define('WPEF_LICENSE_PURCHASE_URL', 'https://alanblair.co/?fluent-cart=instant_checkout&item_id=9&quantity=1');
define('WPEF_LICENSE_ACCOUNT_URL', 'https://alanblair.co/my-account/');
```

### Constants Reference

- **WPEF_LICENSE_ITEM_ID**: Product ID from FluentCart (required)
- **WPEF_LICENSE_API_URL**: WordPress site URL hosting FluentCart (required)
- **WPEF_LICENSE_SLUG**: Plugin slug for identification (required)
- **WPEF_LICENSE_SETTINGS_KEY**: WordPress option key for storing license data (required)
- **WPEF_LICENSE_MENU_TYPE**: Where to add license page (`submenu`, `options`, `menu`)
- **WPEF_LICENSE_MENU_TITLE**: Label for license menu item
- **WPEF_LICENSE_PAGE_TITLE**: Browser title for license page
- **WPEF_LICENSE_PURCHASE_URL**: Link to purchase license
- **WPEF_LICENSE_ACCOUNT_URL**: Link to manage existing licenses

---

## Implementation Checklist

### 1. File Structure (PSR-4 Compliant)

```
/src/Licensing/
  - FluentLicensing.php
  - LicenseSettings.php
  - PluginUpdater.php
```

**Namespace:** `WPE\Favorites\Licensing`
**Autoloading:** PSR-4 via composer.json
**No manual requires needed** - classes are autoloaded by composer

### 2. Define Constants (in wpe-favorites.php)

Add licensing constants after existing plugin constants:

```php
// Define plugin constants (existing)
define('WPEF_VERSION', '1.0.7');
define('WPEF_PLUGIN_FILE', __FILE__);
define('WPEF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPEF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Licensing Configuration Constants (add these)
define('WPEF_LICENSE_ITEM_ID', 1016);
define('WPEF_LICENSE_API_URL', 'https://alanblair.co/');
define('WPEF_LICENSE_SLUG', 'wpe-favorites');
define('WPEF_LICENSE_SETTINGS_KEY', 'wpef_license_settings');
define('WPEF_LICENSE_MENU_TYPE', 'submenu');
define('WPEF_LICENSE_MENU_TITLE', 'License');
define('WPEF_LICENSE_PAGE_TITLE', 'WPE Favorites - License');
define('WPEF_LICENSE_PURCHASE_URL', 'https://alanblair.co/?fluent-cart=instant_checkout&item_id=9&quantity=1');
define('WPEF_LICENSE_ACCOUNT_URL', 'https://alanblair.co/my-account/');
```

### 3. Initialize Licensing (in wpe-favorites.php)

Add licensing initialization using the defined constants (PSR-4 autoloads the classes):

```php
// Initialize licensing on 'init' hook
add_action('init', function() {
    if (!class_exists('WPE\\Favorites\\Licensing\\FluentLicensing')) {
        return;
    }

    $licensing = WPE\Favorites\Licensing\FluentLicensing::getInstance();
    $licensing->register([
        'version'      => WPEF_VERSION,
        'item_id'      => WPEF_LICENSE_ITEM_ID,
        'basename'     => plugin_basename(__FILE__),
        'api_url'      => WPEF_LICENSE_API_URL,
        'slug'         => WPEF_LICENSE_SLUG,
        'settings_key' => WPEF_LICENSE_SETTINGS_KEY,
    ]);

    if (class_exists('WPE\\Favorites\\Licensing\\LicenseSettings')) {
        $licenseSettings = new WPE\Favorites\Licensing\LicenseSettings();
        $licenseSettings->register($licensing, [
            'menu_title'   => WPEF_LICENSE_MENU_TITLE,
            'page_title'   => WPEF_LICENSE_PAGE_TITLE,
            'title'        => WPEF_LICENSE_PAGE_TITLE,
            'purchase_url' => WPEF_LICENSE_PURCHASE_URL,
            'account_url'  => WPEF_LICENSE_ACCOUNT_URL,
            'plugin_name'  => 'WPE Favorites',
        ]);

        $licenseSettings->addPage([
            'type'        => WPEF_LICENSE_MENU_TYPE,
            'page_title'  => WPEF_LICENSE_PAGE_TITLE,
            'menu_title'  => WPEF_LICENSE_MENU_TITLE,
            'parent_slug' => 'wpef-settings',
            'capability'  => 'manage_options',
        ]);
    }
});
```

### 4. Remove GitHub Updater

Delete `src/Updater/GitHubUpdater.php` and remove its registration from `Plugin.php`. FluentCart's `PluginUpdater` replaces it entirely.

### 5. Access Control Logic

**Licensing model:** Lifetime Deal (LTD). Once activated, the license should not expire or become invalid under normal use.

```php
$licensing = WPE\Favorites\Licensing\FluentLicensing::getInstance();
$status = $licensing->getStatus(); // Local check (fast)

if ($status->status !== 'valid') {
    display_license_required_notice();
    return;
}

// Show full plugin interface
```

**What to gate when unlicensed:**
- **Block:** Settings page (rules, limits, audit), frontend output (buttons, shortcodes, queries, Bricks elements)
- **Allow:** Documentation submenu page (always accessible, even unlicensed)
- **Allow:** License activation page

### 6. Status Checking Strategy

- **On Admin Load**: Use `getStatus()` for fast local verification
- **Daily Cron**: Use `getStatus(true)` for remote server validation
- **Handle Status Values**:
  - `valid` → Full plugin access (expected permanent state for LTD)
  - `invalid` → Show notice (should not normally occur for LTD)
  - `disabled` → Show refund/disabled message
  - `unregistered` → Show activation form
  - `error` → Show error + allow retry

### 7. Error Handling Pattern

```php
$result = $licensing->activate($license_key);

if (is_wp_error($result)) {
    $error_message = $result->get_error_message();
    display_error_notice($error_message);
} else {
    $license_data = $result;
    display_success_notice();
}
```

---

## Local/Dev Site Exclusions

### Detection Methods

Check for common local/dev indicators:
- Domain: `localhost`, `.local`, `.test`, `.dev`, `.invalid`
- IP addresses: `127.0.0.1`, `::1`, `10.*.*.*`, `192.168.*.*`, `172.16-31.*.*`
- URLs containing: `staging`, `dev`, `development`
- WordPress constants: `WP_LOCAL_DEV === true`

### Implementation

```php
function is_local_dev_site() {
    $host = parse_url(get_site_url(), PHP_HOST);

    // Check TLDs
    $local_tlds = ['.local', '.test', '.dev', '.invalid', '.localhost'];
    foreach ($local_tlds as $tld) {
        if (str_ends_with($host, $tld)) return true;
    }

    // Check IP ranges
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
    }

    // Check keywords
    $dev_keywords = ['localhost', 'staging', 'dev', 'development'];
    foreach ($dev_keywords as $keyword) {
        if (stripos($host, $keyword) !== false) return true;
    }

    // Check WP constant
    if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) return true;

    return false;
}
```

---

## UI/UX Requirements

### License Not Active State (Unlicensed)

- **Block:** Settings page (rules, limits, audit), all frontend output (buttons, shortcodes, queries, Bricks elements)
- **Allow:** Documentation submenu page (always accessible)
- **Show:**
  - License activation page with activation form
  - Purchase link (`WPEF_LICENSE_PURCHASE_URL`)
  - Account link (`WPEF_LICENSE_ACCOUNT_URL`)

### License Active State (LTD — permanent)

- **Show:** Small "Licensed to: [email/name]" indicator in admin footer or settings
- **Show:** "Manage License" link in plugin settings
- **Allow:** Full plugin functionality (settings, frontend, updates)

### License Disabled State (refund/revocation only)

- **Show:** Notice explaining license was disabled
- **Block:** All functionality except Documentation and License page
- **Note:** This should not occur under normal LTD use — only on refund or manual revocation

---

## Testing Checklist

- [x] Get item_id from FluentCart product page (1016)
- [ ] Test license activation with valid key
- [ ] Test license activation with invalid key
- [ ] Test license deactivation
- [ ] Test local/dev site bypass (all detection methods)
- [ ] Test expired license scenario
- [ ] Test disabled/refunded license scenario
- [ ] Test network/API error scenarios
- [ ] Test automatic updates with active license
- [ ] Verify license status caching and refresh timing
- [ ] Test settings page UI/UX
- [ ] Verify admin access blocking when unlicensed

---

## Critical Action Items

1. ~~**Get Item ID:**~~ Done — `WPEF_LICENSE_ITEM_ID` = 1016
2. **Add Constants:** Add all `WPEF_LICENSE_*` constants to `wpe-favorites.php`
3. **Remove GitHub Updater:** Delete `src/Updater/GitHubUpdater.php` and its registration in `Plugin.php`
4. **Create Classes:** Implement `src/Licensing/` with PSR-4 autoloading
5. **Initialize:** Add licensing init code to `wpe-favorites.php`
6. **Gate Features:** Block settings + frontend when unlicensed; keep Documentation accessible
7. **Add Status Indicator:** Show license status in plugin admin area
8. **Error Handling:** Implement comprehensive WP_Error handling for all licensing methods
9. **Daily Cron:** Set up `wp_schedule_event` for daily remote license validation

---

## Notes

- **Use constants everywhere:** All licensing configuration uses `WPEF_LICENSE_*` constants defined in `wpe-favorites.php`. Never hardcode values.
- License checks should be **fast** - use local `getStatus()` for regular checks
- Remote validation via `getStatus(true)` should run via cron, not on every page load
- Always use `is_wp_error()` before processing results from activate/deactivate/getStatus
- Store license data in WordPress options, not in class properties
- Use singleton pattern: `FluentLicensing::getInstance()` for access throughout plugin
- **Centralized configuration:** All licensing settings are in one place (`wpe-favorites.php`), making updates and maintenance simple
- **Replaces GitHub updater:** Remove `src/Updater/GitHubUpdater.php` — FluentCart's `PluginUpdater` handles all updates
- **LTD model:** Licenses are lifetime — once activated, they should remain valid permanently. No renewal flow needed.
