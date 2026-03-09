<script lang="ts">
  import type { Snippet } from 'svelte';

  type Props = {
    variant?: 'success' | 'warning' | 'danger';
    class?: string;
    style?: string;
    children?: Snippet;
  };

  let {
    variant = 'success',
    class: className = '',
    style,
    children
  }: Props = $props();

  let variantClass = $derived(`wpea-alert--${variant}`);
</script>

<div class="wpea-alert {variantClass} {className}" {style}>
  {#if children}
    {@render children()}
  {/if}
</div>
