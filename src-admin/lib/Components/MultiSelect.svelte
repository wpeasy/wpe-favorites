<script lang="ts">
  import type { ColorVariant, StringOrSnippet, SelectOptionWithDisabled } from '../types';
  import { isSnippet } from '../utils/renderContent';

  type Props = {
    value?: string[];
    id?: string;
    name?: string;
    options: SelectOptionWithDisabled[];
    placeholder?: string;
    disabled?: boolean;
    color?: ColorVariant;
    label?: StringOrSnippet;
    help?: StringOrSnippet;
    searchable?: boolean;
    clearable?: boolean;
    maxItems?: number;
    class?: string;
    style?: string;
    onchange?: (value: string[]) => void;
  };

  let {
    value = $bindable([]),
    id,
    name,
    options = [],
    placeholder = 'Select...',
    disabled = false,
    color,
    label,
    help,
    searchable = true,
    clearable = true,
    maxItems,
    class: className = '',
    style,
    onchange
  }: Props = $props();

  let isOpen = $state(false);
  let searchQuery = $state('');
  let highlightedIndex = $state(-1);
  let containerRef: HTMLDivElement;
  let searchInputRef = $state<HTMLInputElement | null>(null);

  // Generate unique ID for aria-controls
  const listboxId = `wpea-multiselect-listbox-${Math.random().toString(36).slice(2, 9)}`;

  // Helper to get string label for searching (falls back to value if Snippet)
  function getSearchableLabel(opt: SelectOptionWithDisabled): string {
    return typeof opt.label === 'string' ? opt.label : opt.value;
  }

  let filteredOptions = $derived(
    searchQuery
      ? options.filter(opt =>
          getSearchableLabel(opt).toLowerCase().includes(searchQuery.toLowerCase()) &&
          !opt.disabled
        )
      : options.filter(opt => !opt.disabled)
  );

  let selectedOptions = $derived(
    value.map(v => options.find(o => o.value === v)).filter(Boolean) as SelectOptionWithDisabled[]
  );

  let canAddMore = $derived(!maxItems || value.length < maxItems);

  function toggleDropdown() {
    if (disabled) return;
    isOpen = !isOpen;
    if (isOpen) {
      searchQuery = '';
      highlightedIndex = -1;
      setTimeout(() => searchInputRef?.focus(), 10);
    }
  }

  function selectOption(option: SelectOptionWithDisabled) {
    if (!canAddMore && !value.includes(option.value)) return;

    if (value.includes(option.value)) {
      value = value.filter(v => v !== option.value);
    } else {
      value = [...value, option.value];
    }
    searchQuery = '';
    onchange?.(value);
  }

  function removeOption(optionValue: string, event?: Event) {
    event?.stopPropagation();
    value = value.filter(v => v !== optionValue);
    onchange?.(value);
  }

  function clearAll(event: Event) {
    event.stopPropagation();
    value = [];
    onchange?.(value);
  }

  function handleKeydown(event: KeyboardEvent) {
    switch (event.key) {
      case 'Escape':
        isOpen = false;
        break;
      case 'ArrowDown':
        event.preventDefault();
        if (!isOpen) {
          isOpen = true;
        } else {
          highlightedIndex = Math.min(highlightedIndex + 1, filteredOptions.length - 1);
        }
        break;
      case 'ArrowUp':
        event.preventDefault();
        highlightedIndex = Math.max(highlightedIndex - 1, 0);
        break;
      case 'Enter':
        event.preventDefault();
        if (highlightedIndex >= 0 && filteredOptions[highlightedIndex]) {
          selectOption(filteredOptions[highlightedIndex]);
        }
        break;
      case 'Backspace':
        if (searchQuery === '' && value.length > 0) {
          removeOption(value[value.length - 1]);
        }
        break;
    }
  }

  function handleClickOutside(event: MouseEvent) {
    if (containerRef && !containerRef.contains(event.target as Node)) {
      isOpen = false;
    }
  }

  $effect(() => {
    if (isOpen) {
      document.addEventListener('click', handleClickOutside);
      return () => document.removeEventListener('click', handleClickOutside);
    }
  });

  let colorClass = $derived(color ? `wpea-multiselect--${color}` : '');
</script>

<div class="wpea-field {className}" {style}>
  {#if label}
    <label class="wpea-label" for={id}>
      {#if isSnippet(label)}
        {@render label()}
      {:else}
        {label}
      {/if}
    </label>
  {/if}
  <div
    class="wpea-multiselect {colorClass}"
    class:wpea-multiselect--open={isOpen}
    class:wpea-multiselect--disabled={disabled}
    class:wpea-multiselect--has-value={value.length > 0}
    bind:this={containerRef}
  >
    <div
      class="wpea-multiselect__control"
      onclick={toggleDropdown}
      onkeydown={handleKeydown}
      role="combobox"
      aria-controls={listboxId}
      aria-expanded={isOpen}
      aria-haspopup="listbox"
      tabindex={disabled ? -1 : 0}
    >
      <div class="wpea-multiselect__value-container">
        {#each selectedOptions as option}
          <span class="wpea-multiselect__tag">
            <span class="wpea-multiselect__tag-label">
              {#if isSnippet(option.label)}
                {@render option.label()}
              {:else}
                {option.label}
              {/if}
            </span>
            <button
              type="button"
              class="wpea-multiselect__tag-remove"
              onclick={(e) => removeOption(option.value, e)}
              tabindex="-1"
              aria-label="Remove {getSearchableLabel(option)}"
            >
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M10.5 3.5L3.5 10.5M3.5 3.5L10.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </button>
          </span>
        {/each}
        {#if searchable && isOpen}
          <input
            type="text"
            class="wpea-multiselect__input"
            bind:this={searchInputRef}
            bind:value={searchQuery}
            onkeydown={handleKeydown}
            onclick={(e) => e.stopPropagation()}
            placeholder={value.length === 0 ? placeholder : ''}
            {disabled}
          />
        {:else if value.length === 0}
          <span class="wpea-multiselect__placeholder">{placeholder}</span>
        {/if}
      </div>
      <div class="wpea-multiselect__indicators">
        {#if clearable && value.length > 0}
          <button
            type="button"
            class="wpea-multiselect__clear"
            onclick={clearAll}
            tabindex="-1"
            aria-label="Clear all"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path d="M10.5 3.5L3.5 10.5M3.5 3.5L10.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </button>
        {/if}
        <span class="wpea-multiselect__separator"></span>
        <span class="wpea-multiselect__dropdown-indicator">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M3.5 5.25L7 8.75L10.5 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </div>
    </div>

    {#if isOpen}
      <div class="wpea-multiselect__menu" role="listbox" aria-multiselectable="true" id={listboxId}>
        <div class="wpea-multiselect__menu-list">
          {#each filteredOptions as option, i}
            <div
              class="wpea-multiselect__option"
              class:wpea-multiselect__option--selected={value.includes(option.value)}
              class:wpea-multiselect__option--highlighted={i === highlightedIndex}
              class:wpea-multiselect__option--disabled={!canAddMore && !value.includes(option.value)}
              role="option"
              tabindex="-1"
              aria-selected={value.includes(option.value)}
              onclick={() => selectOption(option)}
              onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') selectOption(option); }}
              onmouseenter={() => highlightedIndex = i}
            >
              {#if isSnippet(option.label)}
                {@render option.label()}
              {:else}
                {option.label}
              {/if}
              {#if value.includes(option.value)}
                <svg class="wpea-multiselect__option-check" width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M11.5 4L5.5 10L2.5 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              {/if}
            </div>
          {:else}
            <div class="wpea-multiselect__no-options">No options</div>
          {/each}
        </div>
      </div>
    {/if}
  </div>
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
