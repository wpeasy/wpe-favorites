# Bricks Builder Integration Notes

Technical notes for integrating with Bricks Builder's internal state and APIs.

---

## Plugin Load Order

**Bricks is a theme, not a plugin.** Theme constants like `BRICKS_VERSION` and classes like `\Bricks\Elements` are not available during plugin loading. Any Bricks integration must be deferred to `after_setup_theme` or later:

```php
// WRONG — BRICKS_VERSION is not defined yet during plugin load
BricksIntegration::init(); // checks defined('BRICKS_VERSION') → always false

// CORRECT — defer to after the theme has loaded
add_action('after_setup_theme', [BricksIntegration::class, 'init']);
```

---

## Accessing Bricks Vue State

**CRITICAL:** Always access `.brx-body` from the **main document**, NOT the iframe. The iframe may have a disconnected Vue instance.

```typescript
// Get Vue global properties (define once, reuse everywhere)
const brxBody = document.querySelector('.brx-body') as HTMLElement & {
    __vue_app__?: { config: { globalProperties: Record<string, unknown> } };
};
const props = brxBody?.__vue_app__?.config.globalProperties;
const state = (props as Record<string, unknown>)?.$_state as Record<string, unknown>;
```

### Reactive State (`$_state`)

```typescript
state.activeElement          // { id: "abc123", name: "block", ... }
state.activeElement.id       // Element ID
state.activeElement.cid      // Component ID (if inside a component)
state.selectedElements       // Array of selected elements (bulk edit)
state.activePanel            // Current panel: "element", "class", etc.
state.activeClass            // Currently active global class
state.globalClasses          // All global classes
state.breakpointActive       // Current responsive breakpoint
state.pseudoClassActive      // Active pseudo-class (:hover, :focus, etc.)
```

### Helper Methods (`globalProperties`)

```typescript
// Get element by ID (with version fallback)
const getObj = props.$_getElementObject as (id: string) => BricksElement | null;   // Newer
const getDyn = props.$_getDynamicElementById as (id: string) => BricksElement | null; // Older

// Component elements
const getCmp = props.$_getComponentElementById as (cid: string) => BricksElement | null;

// Global classes
const getCls = props.$_getGlobalClass as (classId: string) => GlobalClass | null;

// Element operations
const create = props.$_createElement as (opts: { name: string }) => BricksElement;
const addNew = props.$_addNewElement as (data: object, opts: object, flag: boolean) => void;
const setActive = props.$_setActiveElement as (el: BricksElement) => void;
const deleteEl = props.$_deleteElement as (el: BricksElement) => void; // Takes FULL object, not ID

// Clipboard
const writeCb = props.$_writeToClipboard as (key: string, content: BricksElement[]) => Promise<void>;
const readCb = props.$_readFromClipboard as (key: string) => Promise<unknown>;
const paste = props.$_pasteElements as () => void;
const copy = props.$_copyElements as (ids: string[]) => void;

// Utilities
const genId = props.$_generateId as () => string;       // 6 random lowercase letters
const showMsg = props.$_showMessage as (msg: string) => void;
const isMobileFirst = (props.$_isMobileFirst as { _value: boolean })._value;
```

### Watching Vue State for Reactivity

Use `window.Vue.watch` instead of polling for near-instant detection of builder changes:

```typescript
const VueGlobal = (window as unknown as Record<string, unknown>).Vue as
    | { watch?: (source: unknown, cb: () => void, opts: { deep: boolean }) => () => void }
    | undefined;

const stopWatcher = VueGlobal?.watch?.(
    () => state,
    () => debouncedCompute(), // 150ms debounce to collapse rapid changes
    { deep: true },
);
// Call stopWatcher() to clean up
```

**Startup timing:** The structure panel DOM (`li.bricks-draggable-item`) renders asynchronously. On first load, retry until elements are found.

### Prefer Vue State Over Custom Tracking

When detecting changes in Bricks Builder, compute from current Vue state rather than tracking with Maps/Sets/timeouts. Vue re-renders cause race conditions with custom tracking.

```typescript
// BAD - Custom tracking with race conditions
let originalLabels: Map<string, string> = new Map();

// GOOD - Compute from current state
function findMismatch(elementId: string): string | null {
    const currentLabel = getLabelFromDom(item);
    const classes = getElementClasses(elementId);
    return classes.find(c => c !== currentLabel) ?? null;
}
```

---

## Static Data (`window.bricksData`)

```typescript
// Page elements (flat array with parent/children references)
bricksData.loadData.content

// Global CSS classes (id -> name mapping)
bricksData.loadData.globalClasses

// Page settings
bricksData.loadData.pageSettings

// Other keys: breakpoints, permissions, themeStyles, colorPalette,
// globalVariables, globalClassesCategories, pseudoClasses, globalSettings,
// templateType, elementsHtml, etc.
```

**Important:** `bricksData.elements` is NOT page elements - it's element type definitions/schemas.

---

## Element Structure

Elements in `bricksData.loadData.content` are a flat array:

```typescript
{
    id: "abc123",           // Unique element ID (6 random lowercase letters)
    name: "block",          // Element type (block, heading, text, etc.)
    parent: "xyz789",       // Parent element ID (0 for root)
    children: ["def456"],   // Array of child element IDs
    label: "Card",          // Custom label (optional, TOP-LEVEL property)
    settings: {
        _cssGlobalClasses: ["classId1", "classId2"],  // Global class IDs (not names!)
        _cssId: "my-custom-id",
        _cssCustom: "...",
        text: "...",
        tag: "div",
    }
}
```

### Element Settings Keys (Verified)

| Feature | Element `name` | Settings Key |
|---------|---------------|-------------|
| Heading tag | `heading` | `settings.tag` (h1-h6), `settings.customTag` when `tag === 'custom'` |
| Image alt | `image` | `settings.altText` |
| Link URL | `text-link`, or any with `tag: 'a'` | `settings.link.url` |

### Element Labels

Labels are a **top-level property**, NOT inside settings:

```typescript
element.label = "Hero Title";           // CORRECT
element.settings._label = "Hero Title"; // WRONG - ignored
```

### Custom Tags (HTML Tag Override)

```typescript
// Standard tag override
element.settings = { text: "Title", tag: "h3" };

// Any custom tag (dl, dt, dd, article, etc.)
element.settings = { text: "Term", tag: "custom", customTag: "dt" };
```

---

## Global Classes

Global classes use ID references, not names:

```typescript
// Element stores class IDs
element.settings._cssGlobalClasses = ["certko", "ywyfdl"]

// Resolve IDs to names
const globalClasses = bricksData.loadData.globalClasses; // [{ id, name, settings }, ...]
function getClassNames(element: BricksElement): string[] {
    const classIds = element.settings?._cssGlobalClasses || [];
    return classIds
        .map(id => globalClasses.find(gc => gc.id === id)?.name)
        .filter(Boolean) as string[];
}
```

### Creating and Applying Global Classes

```typescript
// Create a new global class
const classId = genId();
state.globalClasses.push({ id: classId, name: 'my-class', settings: {} });

// Check trash first
const isTrashed = state.globalClassesTrash?.some(gc => gc.name === 'my-class');

// Apply to element (set as active first, then modify)
const element = getDyn(elementId);
state.activeElement = element;
if (!state.activeElement.settings._cssGlobalClasses) {
    state.activeElement.settings._cssGlobalClasses = [];
}
state.activeElement.settings._cssGlobalClasses.push(classId);
state.rerenderControls = Date.now(); // Trigger UI re-render
```

---

## Element Traversal

```typescript
function collectDescendants(elementId: string, elementMap: Map<string, BricksElement>): string[] {
    const element = elementMap.get(elementId);
    if (!element?.children) return [];

    const descendants: string[] = [];
    for (const childId of element.children) {
        descendants.push(childId);
        descendants.push(...collectDescendants(childId, elementMap));
    }
    return descendants;
}

// Build element map for quick lookup
const elementMap = new Map<string, BricksElement>();
for (const el of bricksData.loadData.content) {
    elementMap.set(el.id, el);
}
```

---

## Preview Iframe

```typescript
const iframe = document.querySelector('#bricks-builder-iframe') as HTMLIFrameElement;
const iframeDoc = iframe?.contentDocument;
const previewElement = iframeDoc?.querySelector(`[data-id="${elementId}"]`);
```

### Component Elements

```typescript
if (state.activeElement?.cid) {
    const componentElement = getCmp(state.activeElement.cid);
}
```

---

## CSS Selectors

```typescript
`.brxe-${element.id}`              // Default (element ID)
`#${element.settings._cssId}`      // Custom CSS ID
`.${globalClassName}`               // Global class
```

---

## Clipboard Operations

Bricks has its own internal clipboard separate from the system clipboard.

```typescript
// Write elements (pass content array only, not full wrapper object)
await writeCb('bricksCopiedElements', elementsArray);

// Read
const data = await readCb('bricksCopiedElements');

// Copy/Paste
copy(['elementId1', 'elementId2']);
paste(); // Inserts after selected element
```

**Clipboard format:**
```typescript
{
    content: [{ id, name, parent, children, settings }],
    source: "bricksCopiedElements",
    sourceUrl: "https://example.com/page",
    version: "2.1.4"
}
```

**Tip:** Focus the iframe before clipboard operations to avoid "Document not focused" errors.

---

## Bricks CSS Variables

When styling custom UI inside the Bricks editor (not the preview iframe):

```css
/* Backgrounds */
--builder-bg-1 / --builder-bg-2 / --builder-bg-3 / --builder-bg-4

/* Text */
--builder-color / --builder-color-2 / --builder-color-dark / --builder-color-light

/* Accent & State */
--builder-color-accent / --builder-color-success / --builder-color-warning
--builder-color-danger / --builder-color-info

/* Controls */
--builder-control-bg / --builder-control-color
```

---

## CodeMirror Editors

Bricks uses CodeMirror 5. **DOM elements are recreated on panel/element switches** — never cache references.

```
.control-code
├── .actions              <- Toolbar (copy, expand buttons)
└── .codemirror-wrapper
    └── .CodeMirror       <- The instance (element.CodeMirror)
```

Always traverse DOM from context to find the current editor:

```typescript
btn.addEventListener('click', () => {
    const controlCode = btn.closest('.actions')?.closest('.control-code');
    const editor = controlCode?.querySelector('.CodeMirror')?.CodeMirror;
    if (editor) {
        const content = editor.getValue();
    }
});
```

---

## Useful Patterns

### Check if Element or Class is Active

```typescript
function isElementActive(): boolean {
    return typeof state?.activeElement === 'object' && !!state.activeElement?.id;
}

function isClassActive(): boolean {
    return state?.activePanel === 'class' && typeof state?.activeClass === 'object';
}
```

### Get Active Object (Element or Class)

```typescript
function getActiveObject(): BricksElement | GlobalClass | null {
    if (isClassActive()) {
        return state.globalClasses.find(gc => gc.id === state.activeClass.id);
    }
    if (state.activeElement?.cid) {
        return getCmp(state.activeElement.cid);
    }
    if (state.activeElement?.id) {
        return getObj(state.activeElement.id);
    }
    return null;
}
```

### Get Visible Element IDs from Structure Panel

```typescript
document.querySelectorAll('li.bricks-draggable-item[data-id]')
```

---

## Custom Queries

Bricks supports custom query types via filters. Register a query type to appear in the Query Loop "Object Type" dropdown, then implement the query logic.

### Registering a Custom Query Type

```php
// 1. Add to the query type dropdown
add_filter('bricks/setup/control_options', function (array $options): array {
    $options['queryTypes']['my_query'] = esc_html__('My Query', 'textdomain');
    return $options;
});

// 2. Execute the query
add_filter('bricks/query/run', function ($results, $query_obj) {
    if ($query_obj->object_type !== 'my_query') return $results;

    $wp_query = new WP_Query([...]);

    // Set pagination properties for Bricks.
    $query_obj->count         = $wp_query->found_posts;
    $query_obj->max_num_pages = $wp_query->max_num_pages;

    return $wp_query->posts;
}, 10, 2);

// 3. Setup post data for dynamic tags
add_filter('bricks/query/loop_object', function ($loop_object, $loop_key, $query_obj) {
    if (!is_object($query_obj) || ($query_obj->object_type ?? '') !== 'my_query') {
        return $loop_object;
    }
    if ($loop_object instanceof WP_Post) {
        global $post;
        $post = $loop_object;
        setup_postdata($post);
    }
    return $loop_object;
}, 10, 3);

// 4. Return correct object ID
add_filter('bricks/query/loop_object_id', function ($object_id, $object, $query_id) {
    if (!$query_id) return $object_id;
    if (\Bricks\Query::get_query_object_type($query_id) !== 'my_query') return $object_id;
    return $object instanceof WP_Post ? $object->ID : $object_id;
}, 10, 3);

// 5. Tell Bricks this is a post loop (enables dynamic data)
add_filter('bricks/query/loop_object_type', function ($object_type, $object, $query_id) {
    if (!$query_id) return $object_type;
    if (\Bricks\Query::get_query_object_type($query_id) !== 'my_query') return $object_type;
    return 'post';
}, 10, 3);
```

### CRITICAL: Filter Parameter Types

Bricks passes `null` for all parameters when calling `bricks/query/loop_object_type`, `bricks/query/loop_object_id`, and `bricks/query/loop_object` **outside of a query loop context** (e.g., during builder initialization). All filter callbacks **must use `mixed` type hints** or guard against null:

```php
// WRONG — fatal error when Bricks passes null
public static function get_object_type(string $object_type, mixed $object, string $query_id): string

// CORRECT — handles null parameters safely
public static function get_object_type(mixed $object_type, mixed $object, mixed $query_id): mixed {
    if (!$query_id) return $object_type;
    // ...
}
```

### Adding Custom Controls for a Query Type

Custom query types don't show the standard Bricks query controls (posts_per_page, orderby, etc.). Add your own controls to nestable elements.

**CRITICAL rules for custom query controls:**

1. **Do NOT use `'group' => 'query'`** — controls with this group are hidden when a custom query type is selected. Use only `'tab' => 'content'`.
2. **Use `['hasLoop', '!=', false]`** (boolean `false`, not empty string `''`) in the `required` condition.
3. **Insert controls after the `'query'` key** in the controls array so they appear in the correct position in the panel, not at the bottom.

```php
$nestable_elements = ['container', 'block', 'div'];

foreach ($nestable_elements as $element) {
    add_filter("bricks/elements/{$element}/controls", function (array $controls): array {
        $required = [['query.objectType', '=', 'my_query'], ['hasLoop', '!=', false]];

        $new_controls = [];
        $new_controls['myPerPage'] = [
            'tab'      => 'content',
            'label'    => esc_html__('Posts Per Page', 'textdomain'),
            'type'     => 'number',
            'default'  => -1,
            'required' => $required,
        ];

        // Insert after 'query' key so controls appear under the Query section.
        $pos = array_search('query', array_keys($controls), true);
        if ($pos === false) {
            return array_merge($controls, $new_controls);
        }
        return array_slice($controls, 0, $pos + 1, true)
             + $new_controls
             + array_slice($controls, $pos + 1, null, true);
    });
}
```

Access these values in `bricks/query/run` via `$query_obj->settings['myPerPage']`.

### Query Object Properties

- `$query_obj->object_type` — the selected query type slug
- `$query_obj->settings` — the `query` sub-array from element settings (includes custom control values)
- `$query_obj->page` — current page number (for pagination)
- `$query_obj->count` — set this to total results for pagination
- `$query_obj->max_num_pages` — set this for pagination

### Pagination

Bricks pagination works automatically when:
1. You set `$query_obj->count` and `$query_obj->max_num_pages` in `bricks/query/run`
2. A Pagination element is linked to the Query Loop element in the Bricks UI

### Array-Type Query Loops (Non-Post Data)

For custom queries returning non-post data (e.g., post type metadata, API responses), use Bricks' built-in **array loop** system instead of implementing custom dynamic data tags.

1. Return **associative arrays** (not stdClass objects) from `bricks/query/run`
2. Set `loop_object_type` to `'array'` so Bricks enables `{query_array}` tags
3. Users access properties with `{query_array @key:'fieldname'}`

```php
// In bricks/query/run — return associative arrays
$items[] = [
    'name'  => $type_name,
    'label' => $type_obj->labels->name,
    'url'   => get_post_type_archive_link($type_name),
];
return $items;

// In bricks/query/loop_object_type — tell Bricks it's an array loop
return 'array';
```

In the Bricks editor, users access data with:
- `{query_array @key:'name'}` → post type slug
- `{query_array @key:'label'}` → display name
- `{query_array @key:'url'}` → archive URL

This avoids the complexity of custom Bricks dynamic data providers. Supports AJAX pagination, Load More, and Infinite Scroll.

---

## Custom Element `render_builder()` Method

Bricks calls `render_builder()` on **every registered element** during builder load (`builder.php` → `element_x_templates()`). The output is injected into the builder iframe footer.

**CRITICAL:** The output MUST be wrapped in a `<script type="text/x-template">` tag. Raw HTML renders visibly at the bottom of the editor.

```php
// WRONG — renders visible heart + text at bottom of every page in the editor
public static function render_builder(): void {
    echo '<button class="my-button"><span class="icon"></span></button>';
}

// CORRECT — hidden Vue template, only used when element is on canvas
public static function render_builder(): void {
    ?>
    <script type="text/x-template" id="tmpl-bricks-element-my-element-name">
        <button class="my-button"><span class="icon"></span></button>
    </script>
    <?php
}
```

The `id` attribute must follow the pattern `tmpl-bricks-element-{$name}` where `{$name}` matches the element's `public $name` property.

---

## WPE Favorites — Bricks Elements & Queries

### Favorite Button Element (`Element_Favorite_Button`)

**File:** `src/Integrations/Bricks/Element_Favorite_Button.php`

Renders the favorite toggle button. Delegates to `Shortcode::render()` with optional labels and pre-rendered icon HTML.

**Control Groups:**

| Group | Controls |
|-------|----------|
| *(ungrouped)* | Post ID (number) |
| **Inactive** | Label (text), Label Typography, Icon (`'type' => 'icon'` with color CSS) |
| **Active** | Label (text), Label Typography, Icon (`'type' => 'icon'` with color CSS) |
| **Hover** | Typography (font + color on `.wpef-button:hover`) |

**Icon controls** use Bricks' native `'type' => 'icon'` which supports Font Awesome, Ionicons, Themify, custom SVG uploads, and custom icon sets. Icons are rendered via `self::render_icon()` (inherited from `\Bricks\Element`) and passed to the shortcode as `icon_html` / `active_icon_html`.

**Dynamic data** is supported on label text fields via `$this->render_dynamic_data()`.

**Rendering flow:**
1. Element resolves labels (with dynamic data) and icons (via `Element::render_icon()`)
2. Passes everything to `Shortcode::render()` as an attributes array
3. Shortcode handles all HTML output (default heart icon fallback, dual-state markup, accessibility)

---

### Favorites Query (`Query_Favorites`)

**File:** `src/Integrations/Bricks/Query_Favorites.php`
**Query type slug:** `wpef_favorites`

Loops through the current user's favorited posts. Returns `WP_Post` objects with full dynamic data support.

**Controls:**

| Control | Type | Notes |
|---------|------|-------|
| Post Type Source | select | `Select` or `Dynamic` |
| Post Type | select | Shown when source is "Select" |
| Post Type (Dynamic) | text | Shown when source is "Dynamic", supports Bricks dynamic data tokens |
| Posts Per Page | number | `-1` for all |
| Order By | select | Title, Date, Modified, ID, Random, Favorited Order |
| Order | select | ASC / DESC |

**Dynamic post type:** When source is "Dynamic", the text field value is resolved via `bricks_render_dynamic_data()` at query time.

---

### Favorite Post Types Query (`Query_Post_Types`)

**File:** `src/Integrations/Bricks/Query_Post_Types.php`
**Query type slug:** `wpef_post_types`

Loops through post types that have favorites enabled. Returns associative arrays for use with `{query_array @key:'...'}` tags.

**Controls:**

| Control | Type | Notes |
|---------|------|-------|
| Post Types | select (multi) | Filter to specific types, or leave empty for all |
| Order By | select | Label or Slug |
| Order | select | ASC / DESC |

**Available query array keys:**
- `{query_array @key:'name'}` — post type slug
- `{query_array @key:'label'}` — plural label
- `{query_array @key:'singular'}` — singular label
- `{query_array @key:'slug'}` — rewrite slug
- `{query_array @key:'archive_url'}` — archive URL
- `{query_array @key:'description'}` — post type description
- `{query_array @key:'icon'}` — dashicon or SVG

---

### User Favorite Count Element (`Element_User_Count`)

**File:** `src/Integrations/Bricks/Element_User_Count.php`

Displays the current user's favorite count, optionally filtered by post type(s).

**Controls:**

| Control | Type | Notes |
|---------|------|-------|
| Post Type Source | select | `Select` or `Dynamic` |
| Post Types | select (multi) | Shown when source is "Select"; filter to specific types or leave empty for all |
| Post Type (Dynamic) | text | Shown when source is "Dynamic"; supports Bricks dynamic data tokens |
| HTML Tag | select | `span` (default), `div`, or `p` |

**Live JS updates:** When filtering by a single post type, the element includes `data-wpef-post-type` so the JS live-count system can update it on add/remove. Multi-type selections render the sum at page load but don't receive per-type JS updates.

---

## Tips

- Always check for undefined/null when accessing nested properties
- Vue state is reactive — values update automatically when selection changes
- `__vue_app__` is Vue 3 specific (Vue 2 used `__vue__`)
- Element IDs and class IDs are 6-char alphanumeric strings
- Use `$_writeToClipboard` + `$_pasteElements` for proper Structure panel integration
- Direct `$_addNewElement` may not always update the Structure panel correctly
- Use `'type' => 'icon'` (not `'svg'`) for icon controls — it supports font icons AND SVG
- Render icons with `Element::render_icon($settings['icon'])`, NOT `Helpers::render_control_icon()`
- Control groups are defined via `set_control_groups()` and referenced with `'group' => 'groupKey'`
- Text fields support dynamic data when rendered with `$this->render_dynamic_data($value)` in the element's `render()` method
- For query filters, use `bricks_render_dynamic_data()` (global function) instead of the element method
