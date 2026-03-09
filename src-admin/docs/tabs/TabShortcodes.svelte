<script lang="ts">
  import { Card, Stack, Alert } from '../../lib';
</script>

<Stack size="lg">
  <Card title="Favorite Button" subtitle="[wpef_button]">
    {#snippet children()}
      <p>Renders a heart toggle button for any post.</p>
      <div class="wpef-code-block">
        <pre><code>[wpef_button]</code></pre>
      </div>

      <h4>Attributes</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>Attribute</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>post_id</code></td><td>Current post</td><td>The post ID to favorite (post type is derived automatically)</td></tr>
          <tr><td><code>label</code></td><td><em>(none)</em></td><td>Text label for the inactive state</td></tr>
          <tr><td><code>active_label</code></td><td><em>(none)</em></td><td>Text label for the active state (falls back to <code>label</code>)</td></tr>
          <tr><td><code>icon_class</code></td><td><em>(none)</em></td><td>CSS icon class for inactive state (e.g. <code>fa-regular fa-heart</code>)</td></tr>
          <tr><td><code>active_icon_class</code></td><td><em>(none)</em></td><td>CSS icon class for active state (e.g. <code>fa-solid fa-heart</code>)</td></tr>
        </tbody>
      </table>

      <p>When no label or icon attributes are set, the button renders a simple heart icon &mdash; identical to the default. Adding any attribute switches to dual-state markup where CSS shows/hides the correct state.</p>

      <h4>Examples</h4>
      <div class="wpef-code-block">
        <pre><code>{@html codeButton}</code></pre>
      </div>
    {/snippet}
  </Card>

  <Card title="User Count" subtitle="[wpef_user_count]">
    {#snippet children()}
      <p>Displays the number of favorites for the current user.</p>
      <div class="wpef-code-block">
        <pre><code>[wpef_user_count]</code></pre>
      </div>

      <h4>Attributes</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>Attribute</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>post_type</code></td><td><em>(all)</em></td><td>Filter count by post type</td></tr>
        </tbody>
      </table>

      <h4>Example</h4>
      <div class="wpef-code-block">
        <pre><code>{@html codeUserCount}</code></pre>
      </div>
    {/snippet}
  </Card>

  <Card title="Post Count" subtitle="[wpef_post_count]">
    {#snippet children()}
      <p>Displays how many users have favorited a specific post.</p>
      <div class="wpef-code-block">
        <pre><code>[wpef_post_count]</code></pre>
      </div>

      <h4>Attributes</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>Attribute</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>post_id</code></td><td>Current post</td><td>The post ID to check</td></tr>
        </tbody>
      </table>
    {/snippet}
  </Card>

  <Card title="Global Count" subtitle="[wpef_global_count]">
    {#snippet children()}
      <p>Displays the total number of favorites across all users.</p>
      <div class="wpef-code-block">
        <pre><code>[wpef_global_count]</code></pre>
      </div>

      <h4>Attributes</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>Attribute</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>post_type</code></td><td><em>(all)</em></td><td>Filter by post type</td></tr>
        </tbody>
      </table>
    {/snippet}
  </Card>

  <Card title="Favorites Loop" subtitle="[wpef_favorites]...[/wpef_favorites]">
    {#snippet children()}
      <p>A loop shortcode that queries the current user's favorited posts. Everything between the opening and closing tags is your template &mdash; it repeats once for each post.</p>

      <h4>How It Works</h4>
      <p>The shortcode sets up a <code>WP_Query</code> loop. For each favorited post, WordPress sets up full post data (<code>the_post()</code>), then renders your inner content. This means:</p>
      <ul>
        <li>Use <code>[wpef_field]</code> to output post title, URL, excerpt, thumbnail, etc.</li>
        <li><code>[wpef_button]</code> automatically detects the current post &mdash; no attributes needed</li>
        <li>Any other shortcode that relies on the current post will also work</li>
      </ul>

      <Alert variant="success">
        {#snippet children()}
          <strong>Self-closing fallback:</strong> Use <code>[wpef_favorites]</code> without closing tag to render a simple linked list with no custom markup needed.
        {/snippet}
      </Alert>

      <h4>Attributes</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>Attribute</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>post_type</code></td><td><em>(all)</em></td><td>Filter by post type</td></tr>
          <tr><td><code>posts_per_page</code></td><td><code>-1</code></td><td>Number of posts (-1 for all)</td></tr>
          <tr><td><code>orderby</code></td><td><code>title</code></td><td>title, date, modified, ID, rand, post__in</td></tr>
          <tr><td><code>order</code></td><td><code>ASC</code></td><td>ASC or DESC</td></tr>
          <tr><td><code>no_results</code></td><td>&mdash;</td><td>Text to show when no favorites exist</td></tr>
          <tr><td><code>class</code></td><td>&mdash;</td><td>CSS class on the default list (self-closing only)</td></tr>
        </tbody>
      </table>

      <h4>Example</h4>
      <div class="wpef-code-block">
        <pre><code>{@html codeFavLoop}</code></pre>
      </div>
    {/snippet}
  </Card>

  <Card title="Loop Field" subtitle="[wpef_field]">
    {#snippet children()}
      <p>Outputs a field from the current post inside a <code>[wpef_favorites]</code> loop.</p>

      <h4>Attributes</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>field</th><th>Output</th></tr>
        </thead>
        <tbody>
          <tr><td><code>title</code></td><td>Post title</td></tr>
          <tr><td><code>url</code></td><td>Permalink</td></tr>
          <tr><td><code>excerpt</code></td><td>Post excerpt</td></tr>
          <tr><td><code>date</code></td><td>Published date</td></tr>
          <tr><td><code>id</code></td><td>Post ID</td></tr>
          <tr><td><code>post_type</code></td><td>Post type slug</td></tr>
          <tr><td><code>thumbnail</code></td><td>Featured image (<code>&lt;img&gt;</code> tag)</td></tr>
        </tbody>
      </table>

      <h4>Example</h4>
      <div class="wpef-code-block">
        <pre><code>{@html codeField}</code></pre>
      </div>
    {/snippet}
  </Card>

  <Card title="Post Types Loop" subtitle="[wpef_post_types]">
    {#snippet children()}
      <p>Renders a list of post types that have favorites enabled.</p>
      <div class="wpef-code-block">
        <pre><code>[wpef_post_types]</code></pre>
      </div>

      <h4>Attributes</h4>
      <table class="wpef-attr-table">
        <thead>
          <tr><th>Attribute</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>link</code></td><td>&mdash;</td><td>Set to <code>archive</code> to wrap labels in archive links</td></tr>
          <tr><td><code>class</code></td><td>&mdash;</td><td>Additional CSS class on the list</td></tr>
        </tbody>
      </table>

      <h4>Example</h4>
      <div class="wpef-code-block">
        <pre><code>{@html codePostTypes}</code></pre>
      </div>
    {/snippet}
  </Card>
</Stack>

<script lang="ts" module>
  const codeButton = `&lt;!-- Default heart icon (auto-detects current post) --&gt;
[wpef_button]

&lt;!-- With labels --&gt;
[wpef_button label="Save" active_label="Saved"]

&lt;!-- With custom icons --&gt;
[wpef_button icon_class="fa-regular fa-bookmark" active_icon_class="fa-solid fa-bookmark"]

&lt;!-- Labels + custom icons --&gt;
[wpef_button label="Save" active_label="Saved" icon_class="fa-regular fa-star" active_icon_class="fa-solid fa-star"]

&lt;!-- For a specific post --&gt;
[wpef_button post_id="42"]`;

  const codeUserCount = `You have [wpef_user_count] favorites.
You have [wpef_user_count post_type="product"] favorited products.`;

  const codeFavLoop = `&lt;!-- Self-closing: renders a default linked list --&gt;
[wpef_favorites]

&lt;!-- As a loop with custom markup --&gt;
[wpef_favorites post_type="product" posts_per_page="10" orderby="date" order="DESC"]
  &lt;div class="card"&gt;
    [wpef_field field="thumbnail"]
    &lt;h3&gt;&lt;a href="[wpef_field field="url"]"&gt;[wpef_field field="title"]&lt;/a&gt;&lt;/h3&gt;
    &lt;p&gt;[wpef_field field="excerpt"]&lt;/p&gt;
    [wpef_button]
  &lt;/div&gt;
[/wpef_favorites]

&lt;!-- With a no-results message --&gt;
[wpef_favorites no_results="You haven't favorited anything yet."]
  &lt;a href="[wpef_field field="url"]"&gt;[wpef_field field="title"]&lt;/a&gt;
[/wpef_favorites]`;

  const codeField = `[wpef_field field="title"]     &rarr; "My Post Title"
[wpef_field field="url"]       &rarr; "https://example.com/my-post/"
[wpef_field field="excerpt"]   &rarr; "Post excerpt text..."
[wpef_field field="date"]      &rarr; "March 9, 2026"
[wpef_field field="thumbnail"] &rarr; &lt;img src="..." /&gt;`;

  const codePostTypes = `&lt;!-- Simple list --&gt;
[wpef_post_types]

&lt;!-- With archive links --&gt;
[wpef_post_types link="archive"]`;
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
