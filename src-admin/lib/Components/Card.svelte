<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { StringOrSnippet } from '../types';
  import { isSnippet } from '../utils/renderContent';

  type Props = {
    muted?: boolean;
    hover?: boolean;
    title?: StringOrSnippet;
    subtitle?: StringOrSnippet;
    class?: string;
    style?: string;
    header?: Snippet;
    actions?: Snippet;
    children?: Snippet;
  };

  let {
    muted = false,
    hover = false,
    title,
    subtitle,
    class: className = '',
    style,
    header,
    actions,
    children
  }: Props = $props();

  let mutedClass = $derived(muted ? 'wpea-card--muted' : '');
  let hoverClass = $derived(hover ? 'wpea-card--hover' : '');
  let hasHeader = $derived(title || subtitle || header || actions);
</script>

<div class="wpea-card {mutedClass} {hoverClass} {className}" {style}>
  {#if hasHeader}
    <div class="wpea-card__header">
      <div>
        {#if header}
          {@render header()}
        {:else}
          {#if title}
            <h3 class="wpea-card__title">
              {#if isSnippet(title)}
                {@render title()}
              {:else}
                {title}
              {/if}
            </h3>
          {/if}
          {#if subtitle}
            <div class="wpea-card__sub">
              {#if isSnippet(subtitle)}
                {@render subtitle()}
              {:else}
                {subtitle}
              {/if}
            </div>
          {/if}
        {/if}
      </div>
      {#if actions}
        <div class="wpea-card__actions">
          {@render actions()}
        </div>
      {/if}
    </div>
  {/if}
  {#if children}
    {@render children()}
  {/if}
</div>
