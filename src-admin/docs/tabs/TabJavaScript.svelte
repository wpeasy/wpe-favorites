<script lang="ts">
  import { Card, Stack } from '../../lib';
</script>

<Stack size="lg">
  <Card title="JavaScript API">
    {#snippet children()}
      <p>The favorites API is available globally on <code>window.WPEF.favorites</code>:</p>
      <div class="wpef-code-block">
        <pre><code>{@html codeApi}</code></pre>
      </div>
    {/snippet}
  </Card>

  <Card title="JavaScript Events">
    {#snippet children()}
      <p>Custom events are dispatched on <code>document</code> for third-party integration:</p>
      <div class="wpef-code-block">
        <pre><code>{@html codeEvents}</code></pre>
      </div>
    {/snippet}
  </Card>

  <Card title="REST API">
    {#snippet children()}
      <p>All endpoints use the <code>wpef/v1</code> namespace. Mutations require authentication via the <code>X-WP-Nonce</code> header.</p>

      <table class="wpef-attr-table">
        <thead>
          <tr><th>Method</th><th>Endpoint</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>GET</code></td><td>/wpef/v1/favorites</td><td>Get current user's favorites</td></tr>
          <tr><td><code>POST</code></td><td>/wpef/v1/favorites</td><td>Add a favorite (body: postId, postType)</td></tr>
          <tr><td><code>DELETE</code></td><td>/wpef/v1/favorites/&#123;postId&#125;</td><td>Remove a single favorite</td></tr>
          <tr><td><code>DELETE</code></td><td>/wpef/v1/favorites</td><td>Clear all favorites (optional: post_types[])</td></tr>
          <tr><td><code>PUT</code></td><td>/wpef/v1/favorites</td><td>Bulk sync (body: favorites array)</td></tr>
          <tr><td><code>GET</code></td><td>/wpef/v1/counts</td><td>Public &mdash; post and global counts</td></tr>
        </tbody>
      </table>

      <p>All mutation endpoints return the full updated <code>&#123; favorites: [...] &#125;</code> array.</p>
    {/snippet}
  </Card>
</Stack>

<script lang="ts" module>
  const codeApi = `// Check if a post is favorited
WPEF.favorites.isFavorited(42); // true | false

// Get all favorites
WPEF.favorites.get(); // [{ postId: 42, postType: 'post' }, ...]

// Add a favorite
await WPEF.favorites.add(42, 'post');

// Remove a favorite
await WPEF.favorites.remove(42);

// Toggle a favorite
await WPEF.favorites.toggle(42, 'post');

// Clear all favorites
await WPEF.favorites.clear();

// Clear favorites for specific post types
await WPEF.favorites.clear(['product', 'post']);`;

  const codeEvents = `// Listen for favorites added
document.addEventListener('wpef:added', (e) => {
  console.log('Added:', e.detail.postId, e.detail.postType);
});

// Listen for favorites removed
document.addEventListener('wpef:removed', (e) => {
  console.log('Removed:', e.detail.postId);
});

// Listen for favorites cleared
document.addEventListener('wpef:cleared', (e) => {
  console.log('Cleared:', e.detail.postTypes); // [] = all
});

// Listen for login sync completed
document.addEventListener('wpef:synced', (e) => {
  console.log('Synced:', e.detail.favorites);
});`;
</script>

<style>
  p {
    margin: 0 0 var(--wpea-space--sm);
    line-height: 1.6;
  }
  code {
    background: var(--wpea-surface--muted);
    padding: 2px 6px;
    border-radius: var(--wpea-radius--sm);
    font-size: var(--wpea-text--sm);
  }
  .wpef-code-block {
    background: var(--wpea-surface--muted);
    border-radius: var(--wpea-radius--md);
    padding: var(--wpea-space--md);
    overflow-x: auto;
  }
  .wpef-code-block pre {
    margin: 0;
  }
  .wpef-code-block code {
    background: none;
    padding: 0;
    font-size: var(--wpea-text--sm);
    line-height: 1.6;
  }
  .wpef-attr-table {
    width: 100%;
    border-collapse: collapse;
    margin: var(--wpea-space--sm) 0;
    font-size: var(--wpea-text--sm);
  }
  .wpef-attr-table th,
  .wpef-attr-table td {
    text-align: left;
    padding: var(--wpea-space--xs) var(--wpea-space--sm);
    border-bottom: 1px solid var(--wpea-surface--divider);
  }
  .wpef-attr-table th {
    font-weight: 600;
    color: var(--wpea-surface--text-muted);
  }
</style>
