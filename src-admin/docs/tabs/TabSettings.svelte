<script lang="ts">
  import { Card, Stack } from '../../lib';
</script>

<Stack size="lg">
  <Card title="Post Type Rules">
    {#snippet children()}
      <p>Post type rules control which post types each WordPress role can favorite. Rules are configured on the <strong>Favorites &rarr; Settings</strong> page under the <strong>Rules</strong> tab.</p>

      <h4>How Rules Work</h4>
      <p>Each rule has three parts:</p>
      <ol>
        <li><strong>Action</strong> &mdash; <code>Include</code> adds post types to the allowed set, <code>Exclude</code> removes them</li>
        <li><strong>Roles</strong> &mdash; Which WordPress roles this rule applies to. "All Roles" matches everyone, including anonymous visitors</li>
        <li><strong>Post Types</strong> &mdash; Which post types are affected by this rule</li>
      </ol>

      <h4>Rule Processing Order</h4>
      <p>Rules are processed <strong>top-to-bottom</strong>, starting from an empty set of enabled post types:</p>
      <ol>
        <li>The <strong>Default rule</strong> runs first (always position 1, cannot be moved or deleted)</li>
        <li>Each subsequent rule either adds to or removes from the set</li>
        <li>Exclude rules take priority &mdash; once a post type is excluded, only an explicit Include in a later rule can re-enable it</li>
      </ol>
      <p>The final set after all matching rules determines which post types the user can favorite.</p>

      <h4>The Default Rule</h4>
      <p>Every installation has a Default rule that cannot be deleted or reordered. It is always the first rule processed and typically includes all public post types for all roles. This ensures a baseline set of enabled post types before any overrides.</p>

      <h4>Anonymous Users</h4>
      <p>Anonymous visitors (not logged in) are only matched by rules with the <strong>"All Roles"</strong> role. Rules targeting specific WordPress roles like Subscriber or Editor do not apply to anonymous users.</p>
    {/snippet}
  </Card>

  <Card title="Examples">
    {#snippet children()}
      <h4>Allow all types except Pages for Subscribers</h4>
      <ol>
        <li><strong>Rule 1 (Default):</strong> Include &rarr; All Roles &rarr; Posts, Pages, Products</li>
        <li><strong>Rule 2:</strong> Exclude &rarr; Subscriber &rarr; Pages</li>
      </ol>
      <p>Result: Subscribers can favorite Posts and Products. All other roles can favorite Posts, Pages, and Products.</p>

      <h4>Only allow Products for a specific role</h4>
      <ol>
        <li><strong>Rule 1 (Default):</strong> Include &rarr; All Roles &rarr; Posts, Pages</li>
        <li><strong>Rule 2:</strong> Include &rarr; Customer &rarr; Products</li>
      </ol>
      <p>Result: Customers can favorite Posts, Pages, and Products. Other roles can only favorite Posts and Pages.</p>

      <h4>Exclude everything, then selectively include</h4>
      <ol>
        <li><strong>Rule 1 (Default):</strong> Include &rarr; All Roles &rarr; Posts</li>
        <li><strong>Rule 2:</strong> Exclude &rarr; Subscriber &rarr; Posts</li>
        <li><strong>Rule 3:</strong> Include &rarr; Subscriber &rarr; Products</li>
      </ol>
      <p>Result: Subscribers can only favorite Products (Posts were excluded, then Products were included). Other roles can favorite Posts.</p>
    {/snippet}
  </Card>

  <Card title="Limits">
    {#snippet children()}
      <p>Limits are configured on the <strong>Limits</strong> tab and apply independently of rules.</p>

      <h4>Per-Type Limits</h4>
      <p>Set a maximum number of favorites per post type for each user. For example, limit users to 5 favorited Products. Leave empty for unlimited.</p>

      <h4>Global Limit</h4>
      <p>Set a maximum total number of favorites across all post types. For example, a global limit of 20 means users can have at most 20 favorites total, regardless of type. Leave empty for unlimited.</p>

      <p>Both limits are enforced on the server when adding favorites and during sync. The client also checks limits before making requests to provide instant feedback.</p>
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
  ol {
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
