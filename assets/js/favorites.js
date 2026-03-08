/**
 * WPE Favorites — frontend module.
 *
 * Manages localStorage, REST sync, button state, and live counts.
 *
 * @package WPE\Favorites
 */

const STORAGE_KEY = 'wpef_favorites';
const SYNCED_KEY  = 'wpef_synced';

/* ------------------------------------------------------------------ */
/*  localStorage helpers                                               */
/* ------------------------------------------------------------------ */

function getLocal() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function setLocal(favorites) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(favorites));
    } catch { /* storage full or unavailable */ }
}

/* ------------------------------------------------------------------ */
/*  REST helpers                                                       */
/* ------------------------------------------------------------------ */

function apiRequest(method, path = '', body = null) {
    const config = window.WPEF;
    if (!config?.restUrl || !config?.nonce) return Promise.resolve(null);

    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce,
        },
    };

    if (body) {
        opts.body = JSON.stringify(body);
    }

    return fetch(`${config.restUrl}/favorites${path}`, opts)
        .then(r => r.ok ? r.json() : null)
        .catch(() => null);
}

/**
 * Fetch counts from the public REST endpoint.
 * Collects post IDs and post types from the DOM to make one request.
 */
function fetchCounts() {
    const config = window.WPEF;
    if (!config?.restUrl) return Promise.resolve(null);

    const postIds   = new Set();
    const postTypes = new Set();

    document.querySelectorAll('[data-wpef-count="post"]').forEach(el => {
        const id = Number(el.dataset.wpefPostId);
        if (id > 0) postIds.add(id);
    });

    document.querySelectorAll('[data-wpef-count="global"][data-wpef-post-type]').forEach(el => {
        const pt = el.dataset.wpefPostType;
        if (pt) postTypes.add(pt);
    });

    const params = new URLSearchParams();
    postIds.forEach(id => params.append('post_ids[]', id));
    postTypes.forEach(pt => params.append('post_types[]', pt));

    return fetch(`${config.restUrl}/counts?${params}`)
        .then(r => r.ok ? r.json() : null)
        .catch(() => null);
}

/* ------------------------------------------------------------------ */
/*  Core API                                                           */
/* ------------------------------------------------------------------ */

function isFavorited(postId) {
    return getLocal().some(f => f.postId === postId);
}

async function addFavorite(postId, postType) {
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
        const res = await apiRequest('POST', '', { postId, postType });
        if (res?.favorites) setLocal(res.favorites);
    }
}

async function removeFavorite(postId) {
    if (!isFavorited(postId)) return;

    const postType = getLocal().find(f => f.postId === postId)?.postType || '';

    // Optimistic: update localStorage first.
    const favorites = getLocal().filter(f => f.postId !== postId);
    setLocal(favorites);
    updateAllButtons();
    updateUserCounts();
    adjustServerCounts(postId, postType, -1);

    emit('wpef:removed', { postId });

    // Sync to server if logged in.
    if (window.WPEF?.isLoggedIn) {
        const res = await apiRequest('DELETE', `/${postId}`);
        if (res?.favorites) setLocal(res.favorites);
    }
}

async function toggleFavorite(postId, postType) {
    if (isFavorited(postId)) {
        await removeFavorite(postId);
    } else {
        await addFavorite(postId, postType);
    }
}

/* ------------------------------------------------------------------ */
/*  Login sync                                                         */
/* ------------------------------------------------------------------ */

async function loginSync() {
    if (!window.WPEF?.isLoggedIn) return;

    // Only sync once per session.
    if (sessionStorage.getItem(SYNCED_KEY)) return;
    sessionStorage.setItem(SYNCED_KEY, '1');

    const local = getLocal();
    const res   = await apiRequest('GET');

    if (!res?.favorites) return;

    const server = res.favorites;

    // Nothing local to merge — just adopt server state.
    if (local.length === 0) {
        setLocal(server);
        updateAllButtons();
        updateUserCounts();
        return;
    }

    // Merge: union by postId.
    const merged = [...server];
    const serverIds = new Set(server.map(f => f.postId));

    for (const fav of local) {
        if (!serverIds.has(fav.postId)) {
            merged.push(fav);
        }
    }

    // Push merged set to server.
    const syncRes = await apiRequest('PUT', '', { favorites: merged });
    const final_  = syncRes?.favorites ?? merged;

    setLocal(final_);
    updateAllButtons();
    updateUserCounts();

    emit('wpef:synced', { favorites: final_ });
}

/* ------------------------------------------------------------------ */
/*  DOM: button state                                                  */
/* ------------------------------------------------------------------ */

function updateAllButtons() {
    const buttons = document.querySelectorAll('[data-wpef-post-id]');
    buttons.forEach(updateButton);
}

function updateButton(btn) {
    const postId = Number(btn.dataset.wpefPostId);
    const active = isFavorited(postId);
    btn.classList.toggle('wpef-button--active', active);
    btn.setAttribute('aria-pressed', String(active));
}

/* ------------------------------------------------------------------ */
/*  DOM: live counts                                                   */
/* ------------------------------------------------------------------ */

/**
 * Update user counts from localStorage (always accurate).
 */
function updateUserCounts() {
    const favorites = getLocal();

    document.querySelectorAll('[data-wpef-count="user"]').forEach(el => {
        const postType = el.dataset.wpefPostType || '';
        const filtered = postType
            ? favorites.filter(f => f.postType === postType)
            : favorites;
        el.textContent = String(filtered.length);
    });
}

/**
 * Apply server counts from the REST response to the DOM.
 */
function applyServerCounts(data) {
    if (!data) return;

    // Global total.
    if (typeof data.global === 'number') {
        document.querySelectorAll('[data-wpef-count="global"]:not([data-wpef-post-type])').forEach(el => {
            el.textContent = String(data.global);
        });
    }

    // Per-post counts.
    if (data.posts) {
        document.querySelectorAll('[data-wpef-count="post"]').forEach(el => {
            const postId = el.dataset.wpefPostId;
            if (postId in data.posts) {
                el.textContent = String(data.posts[postId]);
            }
        });
    }

    // Per-type global counts.
    if (data.types) {
        document.querySelectorAll('[data-wpef-count="global"][data-wpef-post-type]').forEach(el => {
            const pt = el.dataset.wpefPostType;
            if (pt in data.types) {
                el.textContent = String(data.types[pt]);
            }
        });
    }
}

/**
 * Optimistically adjust post and global counts by a delta (+1 or -1).
 */
function adjustServerCounts(postId, postType, delta) {
    // Post counts for this specific post.
    document.querySelectorAll('[data-wpef-count="post"]').forEach(el => {
        if (Number(el.dataset.wpefPostId) === postId) {
            el.textContent = String(Math.max(0, (Number(el.textContent) || 0) + delta));
        }
    });

    // Global total.
    document.querySelectorAll('[data-wpef-count="global"]:not([data-wpef-post-type])').forEach(el => {
        el.textContent = String(Math.max(0, (Number(el.textContent) || 0) + delta));
    });

    // Global by matching post type.
    if (postType) {
        document.querySelectorAll('[data-wpef-count="global"][data-wpef-post-type]').forEach(el => {
            if (el.dataset.wpefPostType === postType) {
                el.textContent = String(Math.max(0, (Number(el.textContent) || 0) + delta));
            }
        });
    }
}

/* ------------------------------------------------------------------ */
/*  DOM: event delegation                                              */
/* ------------------------------------------------------------------ */

function handleClick(e) {
    const btn = e.target.closest('[data-wpef-post-id]');
    if (!btn) return;

    e.preventDefault();
    const postId   = Number(btn.dataset.wpefPostId);
    const postType = btn.dataset.wpefPostType || 'post';
    toggleFavorite(postId, postType);
}

/* ------------------------------------------------------------------ */
/*  Custom events                                                      */
/* ------------------------------------------------------------------ */

function emit(type, detail) {
    document.dispatchEvent(new CustomEvent(type, { detail }));
}

/* ------------------------------------------------------------------ */
/*  Init                                                               */
/* ------------------------------------------------------------------ */

async function init() {
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
        isFavorited,
    },
});
