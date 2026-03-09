<script lang="ts">
  import { Card, Stack, Alert } from '../../lib';
</script>

<Stack size="lg">
  <Alert variant="success">
    {#snippet children()}
      All Bricks elements appear under the <strong>WPE Favorites</strong> category in the Bricks element picker.
    {/snippet}
  </Alert>

  <Card title="Elements">
    {#snippet children()}
      <p>The following custom Bricks elements are available:</p>

      <h4>Favorite Button</h4>
      <p>A toggle button for favoriting posts. Auto-detects the current post inside query loops, or set a specific <strong>Post ID</strong> manually. Controls are organized into three groups:</p>
      <ul>
        <li><strong>Inactive</strong> &mdash; Label, Label Typography, and Icon (with color) for the default state</li>
        <li><strong>Active</strong> &mdash; Label, Label Typography, and Icon (with color) for the favorited state</li>
        <li><strong>Hover</strong> &mdash; Typography (font and color) for the hover state</li>
      </ul>
      <p>Icons use the native Bricks icon picker &mdash; choose from Font Awesome, Ionicons, Themify, custom SVGs, or uploaded icon sets. When no custom icons or labels are set, the button renders a default heart icon.</p>

      <h4>Clear Favorites</h4>
      <p>A button that clears all (or filtered) favorites for the current user. Controls are organized into two groups:</p>
      <ul>
        <li><strong>Button</strong> &mdash; Label and Typography</li>
        <li><strong>Confirmation</strong> &mdash; Confirmation text (double opt-in) and Typography</li>
      </ul>
      <p>Filter by post type using a <strong>Post Type Source</strong> toggle &mdash; choose between a multi-select dropdown or a dynamic data token. The confirmation text defaults to &ldquo;Are you sure?&rdquo; &mdash; the user must click twice to confirm.</p>

      <h4>User Count</h4>
      <p>Displays the current user's total favorites count. Filter by post type using a <strong>Post Type Source</strong> toggle &mdash; choose between a multi-select dropdown or a dynamic data token.</p>

      <h4>Post Count</h4>
      <p>Displays how many users have favorited a specific post. Auto-detects the current post inside query loops. Use the <strong>Post ID Source</strong> toggle to switch between a number input or a dynamic data token.</p>

      <h4>Global Count</h4>
      <p>Displays the total favorites count across all users. Filter by post type using a <strong>Post Type Source</strong> toggle &mdash; choose between a multi-select dropdown or a dynamic data token.</p>
    {/snippet}
  </Card>

  <Card title="Query: User Favorites">
    {#snippet children()}
      <p>Loop through the current user's favorited posts using the <strong>User Favorites</strong> query type.</p>

      <h4>Setup</h4>
      <ol>
        <li>Add a Container, Block, or Div element</li>
        <li>Enable <strong>Query Loop</strong> in the element settings</li>
        <li>Set <strong>Object Type</strong> to <strong>User Favorites</strong></li>
        <li>Configure the query controls that appear:</li>
      </ol>

      <table class="wpef-attr-table">
        <thead>
          <tr><th>Control</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><strong>Post Type Source</strong></td><td>Select</td><td>Choose between a dropdown or a dynamic data token</td></tr>
          <tr><td><strong>Post Type</strong></td><td>All</td><td>Filter favorites by post type (shown when Source is "Select")</td></tr>
          <tr><td><strong>Post Type (Dynamic)</strong></td><td>&mdash;</td><td>Dynamic data token for the post type (shown when Source is "Dynamic")</td></tr>
          <tr><td><strong>Posts Per Page</strong></td><td>-1 (all)</td><td>Number of posts to display</td></tr>
          <tr><td><strong>Order By</strong></td><td>Title</td><td>Title, Date, Modified, Post ID, Random, or Favorited Order</td></tr>
          <tr><td><strong>Order</strong></td><td>ASC</td><td>ASC or DESC</td></tr>
        </tbody>
      </table>

      <h4>Dynamic Data</h4>
      <p>Inside the loop, all standard Bricks dynamic data tags work because the query returns real <code>WP_Post</code> objects:</p>
      <div class="wpef-code-block">
        <pre><code>{@html codeFavQuery}</code></pre>
      </div>

      <h4>Pagination</h4>
      <p>Add a <strong>Pagination</strong> element and it works automatically when <em>Posts Per Page</em> is set to a positive number.</p>
    {/snippet}
  </Card>

  <Card title="Query: Favorite Post Types">
    {#snippet children()}
      <p>Loop through the post types that have favorites enabled. Useful for building navigation or filter UIs.</p>

      <h4>Setup</h4>
      <ol>
        <li>Add a Container, Block, or Div element</li>
        <li>Enable <strong>Query Loop</strong></li>
        <li>Set <strong>Object Type</strong> to <strong>Favorite Post Types</strong></li>
        <li>Configure the query controls that appear:</li>
      </ol>

      <table class="wpef-attr-table">
        <thead>
          <tr><th>Control</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><strong>Post Types</strong></td><td>All</td><td>Multi-select to filter to specific post types, or leave empty for all</td></tr>
          <tr><td><strong>Order By</strong></td><td>Label</td><td>Label or Slug</td></tr>
          <tr><td><strong>Order</strong></td><td>ASC</td><td>Ascending or Descending</td></tr>
        </tbody>
      </table>

      <h4>Dynamic Data</h4>
      <p>This query uses Bricks' <strong>array loop</strong> system. Access properties with the <code>{`{query_array}`}</code> tag:</p>
      <div class="wpef-code-block">
        <pre><code>{@html codePostTypesQuery}</code></pre>
      </div>

      <h4>Available Keys</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>Key</th><th>Example</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>name</code></td><td>product</td><td>Post type slug</td></tr>
          <tr><td><code>label</code></td><td>Products</td><td>Plural display name</td></tr>
          <tr><td><code>singular</code></td><td>Product</td><td>Singular display name</td></tr>
          <tr><td><code>slug</code></td><td>products</td><td>URL rewrite slug</td></tr>
          <tr><td><code>archive_url</code></td><td>/products/</td><td>Archive page URL</td></tr>
          <tr><td><code>description</code></td><td>&mdash;</td><td>Post type description</td></tr>
          <tr><td><code>icon</code></td><td>dashicons-cart</td><td>Menu icon</td></tr>
        </tbody>
      </table>
    {/snippet}
  </Card>

  <Card title="Example: Favorites Page">
    {#snippet children()}
      <p>A typical favorites page in Bricks might look like this:</p>
      <div class="wpef-code-block">
        <pre><code>{@html codeExample}</code></pre>
      </div>
    {/snippet}
  </Card>
</Stack>

<script lang="ts" module>
  const codeFavQuery = `{post_title}
{post_excerpt}
{featured_image}
{post_url}`;

  const codePostTypesQuery = `{query_array @key:'label'}       &rarr; "Products"
{query_array @key:'name'}        &rarr; "product"
{query_array @key:'singular'}    &rarr; "Product"
{query_array @key:'archive_url'} &rarr; "/products/"
{query_array @key:'slug'}        &rarr; "products"`;

  const codeExample = `Section
  &boxvr; Heading: "My Favorites"
  &boxvr; Basic Text: "You have [User Count] favorites"
  &boxvr; Container (Query Loop: User Favorites, Post Type: All)
  &boxv;   &boxvr; Heading: {post_title}
  &boxv;   &boxvr; Image: {featured_image}
  &boxv;   &boxvr; Basic Text: {post_excerpt}
  &boxv;   &boxur; Favorite Button (auto-detects post)
  &boxur; Pagination`;
</script>

<style>
  p {
    margin: 0 0 var(--wpea-space--sm);
    line-height: 1.6;
  }
  h4 {
    margin: var(--wpea-space--md) 0 var(--wpea-space--xs);
    font-weight: 600;
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
