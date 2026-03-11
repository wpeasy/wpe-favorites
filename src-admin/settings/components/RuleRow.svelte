<script lang="ts">
  import type { Rule } from '../types';
  import {
    roleOptions, postTypeOptions,
    rules, getOpenRuleId,
    updateRule, removeRule, toggleRule
  } from '../state.svelte';
  import { Card, Select, MultiSelect, DoubleOptInButton } from '../../lib';
  import type { SelectOption, SelectOptionWithDisabled } from '../../lib/types';

  type Props = {
    rule: Rule;
    index: number;
    isDefault: boolean;
    onDragStart: () => void;
    onDragEnd: () => void;
  };

  let { rule, index, isDefault, onDragStart, onDragEnd }: Props = $props();

  let isOpen = $derived(getOpenRuleId() === rule.id);
  let canDelete = $derived(!isDefault && rules.length > 1);

  const typeOptions: SelectOption[] = [
    { value: 'include', label: 'Include' },
    { value: 'exclude', label: 'Exclude' },
  ];

  let roleSelectOptions: SelectOptionWithDisabled[] = $derived(
    roleOptions.map((r) => ({ value: r.value, label: r.label }))
  );

  let postTypeSelectOptions: SelectOptionWithDisabled[] = $derived(
    postTypeOptions.map((pt) => ({ value: pt.value, label: pt.label }))
  );

  // Summary line for collapsed state.
  let summary = $derived.by(() => {
    const action = rule.type === 'include' ? 'Include' : 'Exclude';
    const roleLabels = rule.roles.includes('all')
      ? 'All Roles'
      : rule.roles
          .map((r) => roleOptions.find((o) => o.value === r)?.label ?? r)
          .join(', ');
    const ptCount = rule.postTypes.length;
    const ptLabel = ptCount === 0 ? 'no post types' : `${ptCount} post type${ptCount > 1 ? 's' : ''}`;
    return `${action} ${ptLabel} for ${roleLabels}`;
  });

  function handleTypeChange(value: string): void {
    updateRule(rule.id, { type: value as 'include' | 'exclude' });
  }

  function handleRolesChange(value: string[]): void {
    const hadAll = rule.roles.includes('all');
    const hasAll = value.includes('all');

    if (hasAll && !hadAll) {
      updateRule(rule.id, { roles: ['all'] });
    } else if (hadAll && value.length > 1) {
      updateRule(rule.id, { roles: value.filter((v) => v !== 'all') });
    } else {
      updateRule(rule.id, { roles: value });
    }
  }

  function handlePostTypesChange(value: string[]): void {
    updateRule(rule.id, { postTypes: value });
  }

  function handleNameInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    updateRule(rule.id, { name: target.value });
  }

  function handleHeaderClick(event: MouseEvent): void {
    const target = event.target as HTMLElement;
    if (target.closest('input, button, .wpef-rule-row__drag-handle')) return;
    toggleRule(rule.id);
  }

  function handleHeaderKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement;
    if (target.closest('input, button')) return;
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      toggleRule(rule.id);
    }
  }
</script>

<Card class="wpef-rule-row{isOpen ? ' wpef-rule-row--open' : ''}{rule.type === 'exclude' ? ' wpef-rule-row--exclude' : ''}">
  {#snippet header()}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
      class="wpef-rule-row__header"
      onclick={handleHeaderClick}
      onkeydown={handleHeaderKeydown}
    >
      {#if !isDefault}
        <div
          class="wpef-rule-row__drag-handle"
          draggable="true"
          ondragstart={onDragStart}
          ondragend={onDragEnd}
          role="button"
          tabindex="0"
          aria-label="Drag to reorder rule {index + 1}"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <circle cx="5.5" cy="3.5" r="1.5"/>
            <circle cx="10.5" cy="3.5" r="1.5"/>
            <circle cx="5.5" cy="8" r="1.5"/>
            <circle cx="10.5" cy="8" r="1.5"/>
            <circle cx="5.5" cy="12.5" r="1.5"/>
            <circle cx="10.5" cy="12.5" r="1.5"/>
          </svg>
        </div>
      {/if}

      <div class="wpef-rule-row__title-area">
        {#if isOpen}
          <input
            type="text"
            class="wpef-rule-row__name-input"
            value={rule.name}
            oninput={handleNameInput}
            onclick={(e) => e.stopPropagation()}
            placeholder="Rule {index + 1}"
            aria-label="Rule name"
          />
        {:else}
          <span class="wpef-rule-row__name">
            {rule.name || `Rule ${index + 1}`}
          </span>
          <span class="wpef-rule-row__summary">{summary}</span>
        {/if}
      </div>

      <div class="wpef-rule-row__header-actions">
        <span class="wpef-rule-row__type-badge" class:wpef-rule-row__type-badge--exclude={rule.type === 'exclude'}>
          {rule.type}
        </span>
        {#if canDelete}
          <DoubleOptInButton
            onClick={() => removeRule(rule.id)}
            iconOnly
            ariaLabel="Delete rule"
            class="wpef-rule-row__delete-btn"
          >
            {#snippet defaultIcon()}
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M10.5 3.5L3.5 10.5M3.5 3.5L10.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            {/snippet}
            {#snippet confirmIcon()}
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M10.5 3.5L3.5 10.5M3.5 3.5L10.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            {/snippet}
          </DoubleOptInButton>
        {/if}
        <span class="wpef-rule-row__chevron" class:wpef-rule-row__chevron--open={isOpen}>
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
            <path d="M3.5 5.25L7 8.75L10.5 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </div>
    </div>
  {/snippet}

  {#if isOpen}
    <div class="wpef-rule-row__body">
      <Select
        label="Action"
        value={rule.type}
        options={typeOptions}
        onchange={handleTypeChange}
      />

      <MultiSelect
        label="Roles"
        value={rule.roles}
        options={roleSelectOptions}
        placeholder="Select roles..."
        searchable={false}
        onchange={handleRolesChange}
      />

      <MultiSelect
        label="Post Types"
        value={rule.postTypes}
        options={postTypeSelectOptions}
        placeholder="Select post types..."
        searchable={false}
        onchange={handlePostTypesChange}
      />
    </div>
  {/if}
</Card>

<style>
  :global(.wpef-rule-row) {
    transition: border-color 0.15s ease;
  }

  :global(.wpef-rule-row--exclude) {
    border-left: 3px solid var(--wpea-color--danger, #dc3545);
  }

  .wpef-rule-row__header {
    display: flex;
    align-items: center;
    gap: var(--wpea-space--sm);
    width: 100%;
    cursor: pointer;
    user-select: none;
  }

  .wpef-rule-row__drag-handle {
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: grab;
    color: var(--wpea-surface--text-muted);
    padding: var(--wpea-space--xs);
    border-radius: var(--wpea-radius--s);
    flex-shrink: 0;
  }

  .wpef-rule-row__drag-handle:hover {
    color: var(--wpea-surface--text);
    background: var(--wpea-surface--muted);
  }

  .wpef-rule-row__drag-handle:active {
    cursor: grabbing;
  }

  .wpef-rule-row__title-area {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .wpef-rule-row__name {
    font-weight: 600;
    font-size: var(--wpea-text--sm);
    color: var(--wpea-surface--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .wpef-rule-row__summary {
    font-size: var(--wpea-text--xs);
    color: var(--wpea-surface--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .wpef-rule-row__name-input {
    font-weight: 600;
    font-size: var(--wpea-text--sm);
    color: var(--wpea-surface--text);
    background: var(--wpea-surface--muted);
    border: 1px solid var(--wpea-surface--border);
    border-radius: var(--wpea-radius--s);
    padding: 2px var(--wpea-space--xs);
    width: 100%;
    outline: none;
  }

  .wpef-rule-row__name-input:focus {
    border-color: var(--wpea-color--primary);
  }

  .wpef-rule-row__header-actions {
    display: flex;
    align-items: center;
    gap: var(--wpea-space--xs);
    flex-shrink: 0;
  }

  .wpef-rule-row__type-badge {
    font-size: var(--wpea-text--xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 2px 6px;
    border-radius: var(--wpea-radius--s);
    background: var(--wpea-color--success-t-20, rgba(40, 167, 69, 0.15));
    color: var(--wpea-color--success, #28a745);
  }

  .wpef-rule-row__type-badge--exclude {
    background: var(--wpea-color--danger-t-20, rgba(220, 53, 69, 0.15));
    color: var(--wpea-color--danger, #dc3545);
  }

  :global(.wpef-rule-row__delete-btn) {
    padding: var(--wpea-space--xs);
  }

  .wpef-rule-row__chevron {
    display: flex;
    align-items: center;
    transition: transform 0.2s ease;
    color: var(--wpea-surface--text-muted);
  }

  .wpef-rule-row__chevron--open {
    transform: rotate(180deg);
  }

  .wpef-rule-row__body {
    display: flex;
    flex-direction: column;
    gap: var(--wpea-space--md);
    padding-top: var(--wpea-space--sm);
  }
</style>
