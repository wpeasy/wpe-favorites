<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { TabItem } from '../lib/types';
  import { VerticalTabs, Card, Stack } from '../lib';
  import TabMain from './tabs/TabMain.svelte';
  import TabShortcodes from './tabs/TabShortcodes.svelte';
  import TabSettings from './tabs/TabSettings.svelte';
  import TabBricks from './tabs/TabBricks.svelte';
  import TabJavaScript from './tabs/TabJavaScript.svelte';
  import TabLicensing from './tabs/TabLicensing.svelte';

  let activeTab = $state('main');
</script>

<div class="wpea">
  <Stack size="lg">
    <div class="wpef-docs-header">
      <h1>WPE Favorites Documentation</h1>
      <p>Everything you need to get started with the favorites system.</p>
    </div>

    <VerticalTabs
      bind:activeTab
      variant="primary"
      tabs={[
        {
          id: 'main',
          label: 'Documentation',
          content: mainContent,
        },
        {
          id: 'settings',
          label: 'Settings',
          content: settingsContent,
        },
        {
          id: 'shortcodes',
          label: 'Shortcodes',
          content: shortcodesContent,
        },
        {
          id: 'javascript',
          label: 'JavaScript & REST',
          content: javascriptContent,
        },
        {
          id: 'bricks',
          label: 'Bricks Integration',
          content: bricksContent,
        },
        {
          id: 'licensing',
          label: 'Licensing',
          content: licensingContent,
        },
      ] satisfies TabItem[]}
    />
  </Stack>
</div>

{#snippet mainContent()}
  <TabMain />
{/snippet}

{#snippet settingsContent()}
  <TabSettings />
{/snippet}

{#snippet shortcodesContent()}
  <TabShortcodes />
{/snippet}

{#snippet javascriptContent()}
  <TabJavaScript />
{/snippet}

{#snippet bricksContent()}
  <TabBricks />
{/snippet}

{#snippet licensingContent()}
  <TabLicensing />
{/snippet}

<style>
  .wpef-docs-header {
    padding: 0 0 var(--wpea-space--md);
  }
  .wpef-docs-header h1 {
    margin: 0 0 var(--wpea-space--xs);
    font-size: var(--wpea-text--2xl);
    font-weight: 600;
  }
  .wpef-docs-header p {
    margin: 0;
    color: var(--wpea-surface--text-muted);
  }
</style>
