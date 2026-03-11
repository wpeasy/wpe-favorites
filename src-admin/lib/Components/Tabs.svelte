<script lang="ts">
  import type { ColorVariant, TabItem } from '../types';
  import { isSnippet } from '../utils/renderContent';

  type Props = {
    tabs: TabItem[];
    variant?: ColorVariant;
    activeTab?: string;
    class?: string;
    style?: string;
    onTabChange?: (tabId: string) => void;
  };

  let {
    tabs = [],
    variant,
    activeTab = $bindable(tabs[0]?.id || ''),
    class: className = '',
    style,
    onTabChange
  }: Props = $props();

  function selectTab(tabId: string) {
    activeTab = tabId;
    onTabChange?.(tabId);
  }

  let variantClass = $derived(variant ? `wpea-tabs--${variant}` : '');
</script>

<div class="wpea-tabs {variantClass} {className}" {style}>
  <div class="wpea-tabs__list" role="tablist">
    {#each tabs as tab}
      <button
        type="button"
        class="wpea-tabs__tab"
        role="tab"
        aria-selected={activeTab === tab.id}
        onclick={() => selectTab(tab.id)}
      >
        {#if isSnippet(tab.label)}
          {@render tab.label()}
        {:else}
          {tab.label}
        {/if}
      </button>
    {/each}
  </div>

  {#each tabs as tab}
    <div
      class="wpea-tabs__panel"
      role="tabpanel"
      aria-hidden={activeTab !== tab.id}
    >
      {@render tab.content()}
    </div>
  {/each}
</div>
