<script lang="ts">
  import { Card, Stack } from '../../lib';
</script>

<Stack size="lg">
  <Card title="Overview">
    {#snippet children()}
      <p>WPE Favorites adds a user favorites system to WordPress. Users can favorite any Post or Custom Post Type from the frontend.</p>
      <ul>
        <li>Favorites persist in <strong>localStorage</strong> for instant, anonymous access</li>
        <li>Automatically syncs to the user's <strong>WordPress profile</strong> when logged in</li>
        <li>Works with any public post type</li>
        <li>Bricks Builder integration with custom elements and query loops</li>
        <li>Shortcode fallbacks for Gutenberg and classic editors</li>
      </ul>
    {/snippet}
  </Card>

  <Card title="How It Works">
    {#snippet children()}
      <h4>Anonymous Users</h4>
      <p>Favorites are stored in the browser's <code>localStorage</code>. No account required. Users can favorite and unfavorite posts instantly.</p>

      <h4>Logged-In Users</h4>
      <p>On page load, the plugin merges any localStorage favorites with the user's server-side favorites (stored as user meta). This ensures favorites persist across devices.</p>

      <h4>Login Sync</h4>
      <p>When a user logs in for the first time in a session, the plugin performs a one-time merge:</p>
      <ol>
        <li>Fetches server favorites via REST API</li>
        <li>Unions with any localStorage favorites</li>
        <li>Pushes the merged set back to the server</li>
        <li>Updates localStorage with the authoritative result</li>
      </ol>
    {/snippet}
  </Card>

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

  <Card title="Counts">
    {#snippet children()}
      <p>The plugin provides three types of favorite counts:</p>
      <ul>
        <li><strong>User Count</strong> &mdash; Number of favorites for the current user (from localStorage)</li>
        <li><strong>Post Count</strong> &mdash; How many users have favorited a specific post (from server)</li>
        <li><strong>Global Count</strong> &mdash; Total favorites across all users, optionally filtered by post type</li>
      </ul>
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
await WPEF.favorites.toggle(42, 'post');`;

  const codeEvents = `// Listen for favorites added
document.addEventListener('wpef:added', (e) => {
  console.log('Added:', e.detail.postId, e.detail.postType);
});

// Listen for favorites removed
document.addEventListener('wpef:removed', (e) => {
  console.log('Removed:', e.detail.postId);
});

// Listen for login sync completed
document.addEventListener('wpef:synced', (e) => {
  console.log('Synced:', e.detail.favorites);
});`;
</script>

<style>
  h4 {
    margin: var(--wpea-space--md) 0 var(--wpea-space--xs);
    font-weight: 600;
  }
  h4:first-child {
    margin-top: 0;
  }
  p {
    margin: 0 0 var(--wpea-space--sm);
    line-height: 1.6;
  }
  ul, ol {
    margin: 0 0 var(--wpea-space--sm);
    padding-left: var(--wpea-space--lg);
    line-height: 1.8;
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
</style>
