<script lang="ts">
  import type { TabItem } from '../lib/types';
  import { Tabs } from '../lib';
  import { getRulesJson, getLimitsJson, getMaxFavorites } from './state.svelte';
  import RulesRepeater from './components/RulesRepeater.svelte';
  import LimitsTab from './components/LimitsTab.svelte';
  import AuditTab from './components/AuditTab.svelte';

  let activeTab = $state('rules');
  let rulesJson = $derived(getRulesJson());
  let limitsJson = $derived(getLimitsJson());
  let maxFavorites = $derived(getMaxFavorites());
</script>

<div class="wpea">
  <Tabs
    bind:activeTab
    tabs={[
      { id: 'rules', label: 'Rules', content: rulesContent },
      { id: 'limits', label: 'Limits', content: limitsContent },
      { id: 'audit', label: 'Audit', content: auditContent },
    ] satisfies TabItem[]}
  />

  <input type="hidden" name="wpef_rules" value={rulesJson} />
  <input type="hidden" name="wpef_limits_json" value={limitsJson} />
  <input type="hidden" name="wpef_max_favorites" value={maxFavorites || ''} />
</div>

{#snippet rulesContent()}
  <div class="wpef-tab-content">
    <p class="wpef-tab-content__description">
      Control which post types each role can favorite. Rules are processed top-to-bottom.
    </p>
    <RulesRepeater />
  </div>
{/snippet}

{#snippet limitsContent()}
  <LimitsTab />
{/snippet}

{#snippet auditContent()}
  <AuditTab />
{/snippet}

<style>
  .wpef-tab-content {
    display: flex;
    flex-direction: column;
    gap: var(--wpea-space--md);
  }

  .wpef-tab-content__description {
    margin: 0;
    color: var(--wpea-surface--text-muted);
    font-size: var(--wpea-text--sm);
  }
</style>
