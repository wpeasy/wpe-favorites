<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { StringOrSnippet, SelectOption } from '../types';
  import { isSnippet } from '../utils/renderContent';

  type Props = {
    value?: string;
    id?: string;
    name?: string;
    disabled?: boolean;
    required?: boolean;
    label?: StringOrSnippet;
    help?: StringOrSnippet;
    class?: string;
    style?: string;
    options?: SelectOption[];
    children?: Snippet;
    onchange?: (value: string) => void;
  };

  let {
    value = $bindable(''),
    id,
    name,
    disabled = false,
    required = false,
    label,
    help,
    class: className = '',
    style,
    options = [],
    children,
    onchange
  }: Props = $props();

  function handleChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    value = target.value;
    onchange?.(value);
  }

  // Helper to get string label for native option (falls back to value if Snippet)
  function getOptionText(option: SelectOption): string {
    return typeof option.label === 'string' ? option.label : option.value;
  }
</script>

{#if label || help}
  <div class="wpea-field">
    {#if label}
      <label class="wpea-label" for={id}>
        {#if isSnippet(label)}
          {@render label()}
        {:else}
          {label}
        {/if}
      </label>
    {/if}
    <select
      class="wpea-select {className}"
      {style}
      {id}
      {name}
      {disabled}
      {required}
      value={value}
      onchange={handleChange}
    >
      {#if children}
        {@render children()}
      {:else}
        {#each options as option}
          <option value={option.value}>{getOptionText(option)}</option>
        {/each}
      {/if}
    </select>
    {#if help}
      <span class="wpea-help">
        {#if isSnippet(help)}
          {@render help()}
        {:else}
          {help}
        {/if}
      </span>
    {/if}
  </div>
{:else}
  <select
    class="wpea-select {className}"
    {style}
    {id}
    {name}
    {disabled}
    {required}
    value={value}
    onchange={handleChange}
  >
    {#if children}
      {@render children()}
    {:else}
      {#each options as option}
        <option value={option.value}>{getOptionText(option)}</option>
      {/each}
    {/if}
  </select>
{/if}
