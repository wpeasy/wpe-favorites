<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { ButtonVariant, Size } from '../types';

  type Props = {
    variant?: ButtonVariant;
    size?: Size;
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
    class?: string;
    style?: string;
    onclick?: () => void;
    children?: Snippet;
  };

  let {
    variant = 'primary',
    size,
    disabled = false,
    type = 'button',
    class: className = '',
    style,
    onclick,
    children
  }: Props = $props();

  let variantClass = $derived(variant ? `wpea-btn--${variant}` : '');
  let sizeClass = $derived(size ? `wpea-btn--${size}` : '');
</script>

<button
  class="wpea-btn {variantClass} {sizeClass} {className}"
  {style}
  {type}
  {disabled}
  onclick={onclick}
>
  {#if children}
    {@render children()}
  {/if}
</button>
