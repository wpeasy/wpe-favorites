<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { StringOrSnippet } from '../types';
  import { isSnippet } from '../utils/renderContent';

  type Props = {
    onClick?: () => void;
    timeout?: number;
    variant?: 'default' | 'success';
    disabled?: boolean;
    defaultIcon?: Snippet;
    confirmIcon?: Snippet;
    defaultLabel?: StringOrSnippet;
    confirmLabel?: StringOrSnippet;
    iconOnly?: boolean;
    ariaLabel?: string;
    class?: string;
    style?: string;
  };

  let {
    onClick,
    timeout = 2000,
    variant = 'default',
    disabled = false,
    defaultIcon,
    confirmIcon,
    defaultLabel = 'Delete',
    confirmLabel = 'Confirm?',
    iconOnly = false,
    ariaLabel,
    class: className = '',
    style
  }: Props = $props();

  // Helper to get string for aria-label (falls back to 'Action' if Snippet)
  function getAriaText(label: StringOrSnippet | undefined): string {
    return typeof label === 'string' ? label : 'Action';
  }

  let isConfirm = $state(false);
  let timeoutId: number | null = null;

  function handleClick() {
    if (disabled) return;

    if (!isConfirm) {
      // First click - enter confirm state
      isConfirm = true;

      // Set timeout to reset
      timeoutId = window.setTimeout(() => {
        resetButton();
      }, timeout);
    } else {
      // Second click - execute action
      if (timeoutId) {
        clearTimeout(timeoutId);
        timeoutId = null;
      }

      // Execute the callback
      onClick?.();

      // Reset button
      resetButton();
    }
  }

  function resetButton() {
    isConfirm = false;
    if (timeoutId) {
      clearTimeout(timeoutId);
      timeoutId = null;
    }
  }

  // Cleanup on unmount
  $effect(() => {
    return () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  });

  const variantClass = $derived(
    variant === 'success' ? 'wpea-btn-double-opt-in--success' : ''
  );

  const iconOnlyClass = $derived(
    iconOnly ? 'wpea-btn-double-opt-in--icon-only' : ''
  );

  const buttonClass = $derived(
    `wpea-btn-double-opt-in ${variantClass} ${iconOnlyClass} ${className}`.trim()
  );

  const buttonAriaLabel = $derived(
    iconOnly ? (ariaLabel || getAriaText(defaultLabel)) : undefined
  );
</script>

<button
  type="button"
  class={buttonClass}
  {style}
  data-confirm={isConfirm}
  disabled={disabled}
  onclick={handleClick}
  aria-label={buttonAriaLabel}
>
  {#if isConfirm}
    {#if confirmIcon}
      {@render confirmIcon()}
    {/if}
    <span class="btn-text">
      {#if isSnippet(confirmLabel)}
        {@render confirmLabel()}
      {:else}
        {confirmLabel}
      {/if}
    </span>
  {:else}
    {#if defaultIcon}
      {@render defaultIcon()}
    {/if}
    <span class="btn-text">
      {#if isSnippet(defaultLabel)}
        {@render defaultLabel()}
      {:else}
        {defaultLabel}
      {/if}
    </span>
  {/if}
</button>
