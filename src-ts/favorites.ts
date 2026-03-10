/**
 * WPE Favorites — frontend module.
 *
 * Manages localStorage, REST sync, button state, and live counts.
 *
 * @package WPE\Favorites
 */

// Module marker — required for `declare global` to work.
export {};

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

interface Favorite {
  postId: number;
  postType: string;
}

interface WPEFConfig {
  restUrl: string;
  nonce: string;
  isLoggedIn: boolean;
  userId: number;
  favorites?: FavoritesAPI;
}

interface FavoritesAPI {
  get: () => Favorite[];
  add: (postId: number, postType: string) => Promise<void>;
  remove: (postId: number) => Promise<void>;
  toggle: (postId: number, postType: string) => Promise<void>;
  clear: (postTypes?: string[]) => Promise<void>;
  isFavorited: (postId: number) => boolean;
}

interface FavoritesResponse {
  favorites: Favorite[];
}

interface CountsResponse {
  global?: number;
  posts?: Record<string, number>;
  types?: Record<string, number>;
}

declare global {
  interface Window {
    WPEF: WPEFConfig;
    WPEF_SIMULATE_ERROR?: boolean;
  }
}

const LEGACY_STORAGE_KEY = 'wpef_favorites';
const LEGACY_SYNCED_KEY = 'wpef_synced';

/* ------------------------------------------------------------------ */
/*  Storage key helpers                                                */
/* ------------------------------------------------------------------ */

function storageKey(): string {
  const uid = window.WPEF?.userId || 0;
  return uid > 0 ? `wpef_${uid}` : 'wpef';
}

function anonKey(): string {
  return 'wpef';
}

function userKey(): string {
  return `wpef_${window.WPEF?.userId || 0}`;
}

function syncedKey(): string {
  return `wpef_synced_${window.WPEF?.userId || 0}`;
}

/* ------------------------------------------------------------------ */
/*  localStorage helpers                                               */
/* ------------------------------------------------------------------ */

function readKey(key: string): Favorite[] {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function writeKey(key: string, favorites: Favorite[]): void {
  try {
    localStorage.setItem(key, JSON.stringify(favorites));
  } catch {
    /* storage full or unavailable */
  }
}

function getLocal(): Favorite[] {
  return readKey(storageKey());
}

function setLocal(favorites: Favorite[]): void {
  writeKey(storageKey(), favorites);
}

/* ------------------------------------------------------------------ */
/*  Legacy key migration                                               */
/* ------------------------------------------------------------------ */

function migrateLegacyKey(): void {
  try {
    const raw = localStorage.getItem(LEGACY_STORAGE_KEY);
    if (!raw) return;

    const data: Favorite[] = JSON.parse(raw);
    if (!Array.isArray(data) || data.length === 0) {
      localStorage.removeItem(LEGACY_STORAGE_KEY);
      return;
    }

    // Copy to the appropriate new key (user key if logged in, anon key otherwise).
    const targetKey = storageKey();
    const existing = readKey(targetKey);

    if (existing.length === 0) {
      writeKey(targetKey, data);
    } else {
      // Merge: union by postId.
      const merged = [...existing];
      const ids = new Set(existing.map((f) => f.postId));
      for (const fav of data) {
        if (!ids.has(fav.postId)) merged.push(fav);
      }
      writeKey(targetKey, merged);
    }

    localStorage.removeItem(LEGACY_STORAGE_KEY);
  } catch {
    // Corrupt legacy data — just remove it.
    localStorage.removeItem(LEGACY_STORAGE_KEY);
  }

  // Migrate legacy session flag.
  try {
    if (sessionStorage.getItem(LEGACY_SYNCED_KEY)) {
      sessionStorage.setItem(syncedKey(), '1');
      sessionStorage.removeItem(LEGACY_SYNCED_KEY);
    }
  } catch {
    /* sessionStorage unavailable */
  }
}

/* ------------------------------------------------------------------ */
/*  REST helpers                                                       */
/* ------------------------------------------------------------------ */

async function apiRequest<T>(method: string, path = '', body: unknown = null): Promise<T | null> {
  const config = window.WPEF;
  if (!config?.restUrl || !config?.nonce) return null;

  const opts: RequestInit = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
  };

  if (body) {
    opts.body = JSON.stringify(body);
  }

  // TODO: Remove after testing — simulate sync error.
  if (window.WPEF_SIMULATE_ERROR) {
    throw new Error('Simulated sync failure for testing');
  }

  const response = await fetch(`${config.restUrl}/favorites${path}`, opts);

  if (!response.ok) {
    let message = `Server error (${response.status})`;
    try {
      const err = await response.json();
      if (err?.message) message = err.message;
    } catch {
      /* non-JSON error body */
    }
    throw new Error(message);
  }

  return response.json();
}

/**
 * Fetch counts from the public REST endpoint.
 * Collects post IDs and post types from the DOM to make one request.
 */
async function fetchCounts(): Promise<CountsResponse | null> {
  const config = window.WPEF;
  if (!config?.restUrl) return null;

  const postIds = new Set<number>();
  const postTypes = new Set<string>();

  document.querySelectorAll<HTMLElement>('[data-wpef-count="post"]').forEach((el) => {
    const id = Number(el.dataset.wpefPostId);
    if (id > 0) postIds.add(id);
  });

  document.querySelectorAll<HTMLElement>('[data-wpef-count="global"][data-wpef-post-type]').forEach((el) => {
    const pt = el.dataset.wpefPostType;
    if (pt) postTypes.add(pt);
  });

  const params = new URLSearchParams();
  postIds.forEach((id) => params.append('post_ids[]', String(id)));
  postTypes.forEach((pt) => params.append('post_types[]', pt));

  try {
    const response = await fetch(`${config.restUrl}/counts?${params}`);
    return response.ok ? response.json() : null;
  } catch {
    return null;
  }
}

/* ------------------------------------------------------------------ */
/*  Toast notifications                                                */
/* ------------------------------------------------------------------ */

function showToast(message: string): void {
  const toast = document.createElement('div');
  toast.className = 'wpef-toast wpef-toast--error';
  toast.setAttribute('role', 'alert');
  toast.textContent = message;
  document.body.appendChild(toast);

  // Trigger reflow then add visible class for CSS transition.
  void toast.offsetHeight;
  toast.classList.add('wpef-toast--visible');

  setTimeout(() => {
    toast.classList.remove('wpef-toast--visible');
    toast.addEventListener('transitionend', () => toast.remove());
  }, 5000);
}

/* ------------------------------------------------------------------ */
/*  Core API                                                           */
/* ------------------------------------------------------------------ */

function isFavorited(postId: number): boolean {
  return getLocal().some((f) => f.postId === postId);
}

async function addFavorite(postId: number, postType: string): Promise<void> {
  if (isFavorited(postId)) return;

  // Optimistic: update localStorage first.
  const favorites = getLocal();
  favorites.push({ postId, postType });
  setLocal(favorites);
  updateAllButtons();
  updateUserCounts();
  adjustServerCounts(postId, postType, 1);

  emit('wpef:added', { postId, postType });

  // Sync to server if logged in.
  if (window.WPEF?.isLoggedIn) {
    try {
      const res = await apiRequest<FavoritesResponse>('POST', '', { postId, postType });
      if (res?.favorites) setLocal(res.favorites);
    } catch (err) {
      showToast(`Failed to sync favorite: ${(err as Error).message}`);
    }
  }
}

async function removeFavorite(postId: number): Promise<void> {
  if (!isFavorited(postId)) return;

  const postType = getLocal().find((f) => f.postId === postId)?.postType || '';

  // Optimistic: update localStorage first.
  const favorites = getLocal().filter((f) => f.postId !== postId);
  setLocal(favorites);
  updateAllButtons();
  updateUserCounts();
  adjustServerCounts(postId, postType, -1);

  emit('wpef:removed', { postId });

  // Sync to server if logged in.
  if (window.WPEF?.isLoggedIn) {
    try {
      const res = await apiRequest<FavoritesResponse>('DELETE', `/${postId}`);
      if (res?.favorites) setLocal(res.favorites);
    } catch (err) {
      showToast(`Failed to sync favorite: ${(err as Error).message}`);
    }
  }
}

async function toggleFavorite(postId: number, postType: string): Promise<void> {
  if (isFavorited(postId)) {
    await removeFavorite(postId);
  } else {
    await addFavorite(postId, postType);
  }
}

/* ------------------------------------------------------------------ */
/*  Clear                                                              */
/* ------------------------------------------------------------------ */

async function clearFavorites(postTypes: string[] = []): Promise<void> {
  const favorites = getLocal();

  if (favorites.length === 0) return;

  // Optimistic: clear localStorage first.
  if (postTypes.length === 0) {
    setLocal([]);
  } else {
    setLocal(favorites.filter((f) => !postTypes.includes(f.postType)));
  }

  updateAllButtons();
  updateUserCounts();

  emit('wpef:cleared', { postTypes });

  // Sync to server if logged in.
  if (window.WPEF?.isLoggedIn) {
    try {
      const body = postTypes.length > 0 ? { post_types: postTypes } : {};
      const res = await apiRequest<FavoritesResponse>('DELETE', '', body);
      if (res?.favorites) setLocal(res.favorites);
    } catch (err) {
      showToast(`Failed to clear favorites: ${(err as Error).message}`);
    }
  }
}

/* ------------------------------------------------------------------ */
/*  Login sync                                                         */
/* ------------------------------------------------------------------ */

function mergeFavorites(a: Favorite[], b: Favorite[]): Favorite[] {
  const merged = [...a];
  const ids = new Set(a.map((f) => f.postId));
  for (const fav of b) {
    if (!ids.has(fav.postId)) merged.push(fav);
  }
  return merged;
}

async function syncToServer(favorites: Favorite[]): Promise<Favorite[]> {
  const res = await apiRequest<FavoritesResponse>('PUT', '', { favorites });
  return res?.favorites ?? favorites;
}

function applyFavorites(favorites: Favorite[]): void {
  setLocal(favorites);
  updateAllButtons();
  updateUserCounts();
}

function showSyncPrompt(anonFavs: Favorite[], serverFavs: Favorite[]): void {
  const banner = document.createElement('div');
  banner.className = 'wpef-sync-prompt';
  banner.setAttribute('role', 'dialog');
  banner.setAttribute('aria-label', 'Favorites sync');

  const msg = document.createElement('p');
  msg.className = 'wpef-sync-prompt__message';
  msg.textContent = 'This device has unsaved favorites from a previous session. Would you like to add them to your favorites?';

  const actions = document.createElement('div');
  actions.className = 'wpef-sync-prompt__actions';

  const addBtn = document.createElement('button');
  addBtn.type = 'button';
  addBtn.className = 'wpef-sync-prompt__btn wpef-sync-prompt__btn--add';
  addBtn.textContent = 'Add to mine';

  const discardBtn = document.createElement('button');
  discardBtn.type = 'button';
  discardBtn.className = 'wpef-sync-prompt__btn wpef-sync-prompt__btn--discard';
  discardBtn.textContent = 'Discard';

  actions.appendChild(addBtn);
  actions.appendChild(discardBtn);
  banner.appendChild(msg);
  banner.appendChild(actions);

  const dismiss = (): void => {
    banner.classList.remove('wpef-sync-prompt--visible');
    banner.addEventListener('transitionend', () => banner.remove());
  };

  addBtn.addEventListener('click', async () => {
    dismiss();
    try {
      const merged = mergeFavorites(serverFavs, anonFavs);
      const final_ = await syncToServer(merged);
      applyFavorites(final_);
      emit('wpef:synced', { favorites: final_ });
    } catch (err) {
      showToast(`Failed to sync favorites: ${(err as Error).message}`);
    }
  });

  discardBtn.addEventListener('click', () => {
    dismiss();
    applyFavorites(serverFavs);
  });

  document.body.appendChild(banner);
  void banner.offsetHeight;
  banner.classList.add('wpef-sync-prompt--visible');
}

async function loginSync(): Promise<void> {
  if (!window.WPEF?.isLoggedIn) return;

  const sKey = syncedKey();
  const alreadySynced = !!sessionStorage.getItem(sKey);

  // Always fetch latest from server for logged-in users.
  try {
    const res = await apiRequest<FavoritesResponse>('GET');
    const server = res?.favorites ?? [];

    // On repeat loads (already synced this session), just refresh from server.
    if (alreadySynced) {
      if (server.length > 0) {
        applyFavorites(server);
      }
      return;
    }

    // First load this session — run full merge decision tree.
    sessionStorage.setItem(sKey, '1');

    const aKey = anonKey();
    const anonFavs = readKey(aKey);
    const userFavs = readKey(userKey());
    const userKeyEmpty = userFavs.length === 0;

    // Always clear the anon key at the end (consumed or discarded).
    const clearAnon = (): void => {
      try { localStorage.removeItem(aKey); } catch { /* */ }
    };

    if (server.length > 0) {
      // Server is truth → write to user key.
      applyFavorites(server);

      if (anonFavs.length > 0 && userKeyEmpty) {
        // Scenario 6: anon data + server data + no user key → prompt.
        showSyncPrompt(anonFavs, server);
        // Anon key cleared regardless of choice (prompt handlers don't need it).
        clearAnon();
      } else {
        // Scenarios 2, 4, 8: server wins, discard anon silently.
        clearAnon();
      }

      emit('wpef:synced', { favorites: server });
    } else {
      // Server is empty.
      if (userFavs.length > 0) {
        // Scenario 3: user key has data, server empty → sync to server.
        clearAnon();
        const final_ = await syncToServer(userFavs);
        applyFavorites(final_);
        emit('wpef:synced', { favorites: final_ });
      } else if (anonFavs.length > 0) {
        // Scenario 5: anon data, no user key, no server → auto-assign.
        clearAnon();
        const final_ = await syncToServer(anonFavs);
        applyFavorites(final_);
        emit('wpef:synced', { favorites: final_ });
      } else {
        // Scenario 1: all empty → start fresh.
        clearAnon();
      }
    }
  } catch (err) {
    showToast(`Failed to sync favorites: ${(err as Error).message}`);
  }
}

/* ------------------------------------------------------------------ */
/*  DOM: button state                                                  */
/* ------------------------------------------------------------------ */

function updateAllButtons(): void {
  const buttons = document.querySelectorAll<HTMLElement>('[data-wpef-post-id]');
  buttons.forEach(updateButton);
}

function updateButton(btn: HTMLElement): void {
  const postId = Number(btn.dataset.wpefPostId);
  const active = isFavorited(postId);
  btn.classList.toggle('wpef-button--active', active);
  btn.setAttribute('aria-pressed', String(active));

  // Update aria-label with post title when no visible label text exists.
  const title = btn.dataset.wpefPostTitle;
  if (title && !btn.querySelector('.wpef-button__label')) {
    const label = active
      ? `Remove ${title} from favorites`
      : `Add ${title} to favorites`;
    btn.setAttribute('aria-label', label);
  }
}

/* ------------------------------------------------------------------ */
/*  DOM: live counts                                                   */
/* ------------------------------------------------------------------ */

/**
 * Update user counts from localStorage (always accurate).
 */
function updateUserCounts(): void {
  const favorites = getLocal();

  document.querySelectorAll<HTMLElement>('[data-wpef-count="user"]').forEach((el) => {
    const postType = el.dataset.wpefPostType || '';
    const filtered = postType ? favorites.filter((f) => f.postType === postType) : favorites;
    el.textContent = String(filtered.length);
  });
}

/**
 * Apply server counts from the REST response to the DOM.
 */
function applyServerCounts(data: CountsResponse | null): void {
  if (!data) return;

  // Global total.
  if (typeof data.global === 'number') {
    document.querySelectorAll<HTMLElement>('[data-wpef-count="global"]:not([data-wpef-post-type])').forEach((el) => {
      el.textContent = String(data.global);
    });
  }

  // Per-post counts.
  if (data.posts) {
    document.querySelectorAll<HTMLElement>('[data-wpef-count="post"]').forEach((el) => {
      const postId = el.dataset.wpefPostId;
      if (postId && postId in data.posts!) {
        el.textContent = String(data.posts![postId]);
      }
    });
  }

  // Per-type global counts.
  if (data.types) {
    document.querySelectorAll<HTMLElement>('[data-wpef-count="global"][data-wpef-post-type]').forEach((el) => {
      const pt = el.dataset.wpefPostType;
      if (pt && pt in data.types!) {
        el.textContent = String(data.types![pt]);
      }
    });
  }
}

/**
 * Optimistically adjust post and global counts by a delta (+1 or -1).
 */
function adjustServerCounts(postId: number, postType: string, delta: number): void {
  // Post counts for this specific post.
  document.querySelectorAll<HTMLElement>('[data-wpef-count="post"]').forEach((el) => {
    if (Number(el.dataset.wpefPostId) === postId) {
      el.textContent = String(Math.max(0, (Number(el.textContent) || 0) + delta));
    }
  });

  // Global total.
  document.querySelectorAll<HTMLElement>('[data-wpef-count="global"]:not([data-wpef-post-type])').forEach((el) => {
    el.textContent = String(Math.max(0, (Number(el.textContent) || 0) + delta));
  });

  // Global by matching post type.
  if (postType) {
    document.querySelectorAll<HTMLElement>('[data-wpef-count="global"][data-wpef-post-type]').forEach((el) => {
      if (el.dataset.wpefPostType === postType) {
        el.textContent = String(Math.max(0, (Number(el.textContent) || 0) + delta));
      }
    });
  }
}

/* ------------------------------------------------------------------ */
/*  DOM: event delegation                                              */
/* ------------------------------------------------------------------ */

function handleClick(e: Event): void {
  const target = e.target as HTMLElement;

  // Clear button.
  const clearBtn = target.closest<HTMLElement>('[data-wpef-clear]');
  if (clearBtn) {
    e.preventDefault();
    handleClearClick(clearBtn);
    return;
  }

  // Favorite toggle button.
  const btn = target.closest<HTMLElement>('[data-wpef-post-id]');
  if (!btn) return;

  e.preventDefault();
  const postId = Number(btn.dataset.wpefPostId);
  const postType = btn.dataset.wpefPostType || 'post';
  toggleFavorite(postId, postType);
}

/**
 * Handle clear button clicks with optional double opt-in confirmation.
 */
function handleClearClick(btn: HTMLElement): void {
  const confirmText = btn.dataset.wpefClearConfirm;

  // No confirmation required — clear immediately.
  if (!confirmText) {
    const types = parseClearTypes(btn);
    clearFavorites(types);
    return;
  }

  // Already confirming — execute the clear.
  if (btn.classList.contains('wpef-clear--confirming')) {
    btn.classList.remove('wpef-clear--confirming');
    btn.textContent = btn.dataset.wpefClearLabel || '';
    const types = parseClearTypes(btn);
    clearFavorites(types);
    return;
  }

  // First click — enter confirmation state.
  btn.dataset.wpefClearLabel = btn.textContent || '';
  btn.textContent = confirmText;
  btn.classList.add('wpef-clear--confirming');

  // Cancel on Escape key.
  const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      cancelClearConfirm(btn);
      btn.removeEventListener('keydown', onKeydown);
    }
  };
  btn.addEventListener('keydown', onKeydown);

  // Auto-revert after 3 seconds.
  setTimeout(() => {
    if (btn.classList.contains('wpef-clear--confirming')) {
      cancelClearConfirm(btn);
      btn.removeEventListener('keydown', onKeydown);
    }
  }, 3000);
}

function cancelClearConfirm(btn: HTMLElement): void {
  btn.classList.remove('wpef-clear--confirming');
  btn.textContent = btn.dataset.wpefClearLabel || '';
}

function parseClearTypes(btn: HTMLElement): string[] {
  const raw = btn.dataset.wpefClearTypes;
  if (!raw) return [];
  return raw.split(',').map((s) => s.trim()).filter(Boolean);
}

/* ------------------------------------------------------------------ */
/*  Custom events                                                      */
/* ------------------------------------------------------------------ */

function emit(type: string, detail: Record<string, unknown>): void {
  document.dispatchEvent(new CustomEvent(type, { detail }));
}

/* ------------------------------------------------------------------ */
/*  Init                                                               */
/* ------------------------------------------------------------------ */

async function init(): Promise<void> {
  migrateLegacyKey();

  document.addEventListener('click', handleClick);
  updateAllButtons();
  updateUserCounts();

  // Fetch server counts for post/global elements.
  const counts = await fetchCounts();
  applyServerCounts(counts);

  loginSync();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

// Expose public API on window for builder integrations.
window.WPEF = Object.assign(window.WPEF || {}, {
  favorites: {
    get: getLocal,
    add: addFavorite,
    remove: removeFavorite,
    toggle: toggleFavorite,
    clear: clearFavorites,
    isFavorited,
  },
} satisfies { favorites: FavoritesAPI });
