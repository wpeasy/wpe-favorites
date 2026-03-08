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

## Tips

- Always check for undefined/null when accessing nested properties
- Vue state is reactive — values update automatically when selection changes
- `__vue_app__` is Vue 3 specific (Vue 2 used `__vue__`)
- Element IDs and class IDs are 6-char alphanumeric strings
- Use `$_writeToClipboard` + `$_pasteElements` for proper Structure panel integration
- Direct `$_addNewElement` may not always update the Structure panel correctly
