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
</style>
