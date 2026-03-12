<script lang="ts">
  import { Card, Stack } from '../../lib';
</script>

<Stack size="lg">
  <Card title="License Activation">
    {#snippet children()}
      <p>WPE Favorites requires a valid license to access settings and receive automatic updates. All frontend features (buttons, shortcodes, Bricks elements, and query loops) work without a license.</p>

      <h4>How to Activate</h4>
      <p>Navigate to <strong>Favorites &rarr; License</strong> in the WordPress admin menu. Enter your license key and click <strong>Activate License</strong>.</p>

      <h4>Unlimited Domains</h4>
      <p>Your license supports <strong>unlimited domain activations</strong>. You can use the same license key across as many WordPress installations as you need.</p>
    {/snippet}
  </Card>

  <Card title="What Requires a License">
    {#snippet children()}
      <h4>Requires a License</h4>
      <ul>
        <li>Settings page (rules, limits, audit)</li>
        <li>Automatic updates</li>
      </ul>

      <h4>Works Without a License</h4>
      <ul>
        <li>Favorite buttons (shortcode and Bricks element)</li>
        <li>Count shortcodes and Bricks elements</li>
        <li>Favorites loop shortcode and Bricks query</li>
        <li>Post types loop shortcode and Bricks query</li>
        <li>Clear favorites shortcode and Bricks element</li>
        <li>REST API endpoints</li>
        <li>Documentation page</li>
        <li>License activation page</li>
      </ul>
    {/snippet}
  </Card>

  <Card title="WordPress Multisite">
    {#snippet children()}
      <p>On WordPress multisite, the license is stored <strong>network-wide</strong>. You only need to activate once &mdash; the license is automatically valid for all sites in the network.</p>
      <p>The license page is available from <strong>Favorites &rarr; License</strong> on any site in the network. Activating or deactivating on any site applies to the entire network.</p>
      <p>Plugin settings (rules, limits) remain <strong>per-site</strong> &mdash; each site in the network can have its own configuration.</p>
    {/snippet}
  </Card>

  <Card title="Local &amp; Development Sites">
    {#snippet children()}
      <p>License checks are <strong>automatically bypassed</strong> on local and development environments. The plugin detects these by domain:</p>
      <ul>
        <li><code>.local</code>, <code>.test</code>, <code>.dev</code>, <code>.localhost</code>, <code>.invalid</code> TLDs</li>
        <li>Private IP addresses (127.0.0.1, 192.168.x.x, etc.)</li>
        <li>Domains containing <code>localhost</code> or <code>staging</code></li>
      </ul>
      <p>On these environments, all features work without entering a license key.</p>
    {/snippet}
  </Card>

  <Card title="Deactivation">
    {#snippet children()}
      <p>To deactivate your license, go to <strong>Favorites &rarr; License</strong> and click <strong>Deactivate License</strong>. This releases the activation so you can use the key on another installation if needed.</p>
      <p>Since licenses are unlimited domain, deactivation is typically only needed if you are retiring a site.</p>
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
  ul {
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
