<script lang="ts">
  import { getAuditRows, postTypeOptions } from '../state.svelte';

  let rows = $derived(getAuditRows());
</script>

<div class="wpef-audit">
  <p class="wpef-audit__description">
    Live preview of how the current rules resolve for each role. Changes update instantly as you edit rules.
  </p>

  <div class="wpef-audit__table-wrap">
    <table class="wpef-audit__table">
      <thead>
        <tr>
          <th class="wpef-audit__th-role">Role</th>
          {#each postTypeOptions as pt}
            <th class="wpef-audit__th-type">{pt.label}</th>
          {/each}
        </tr>
      </thead>
      <tbody>
        {#each rows as row}
          <tr>
            <td class="wpef-audit__td-role">{row.roleLabel}</td>
            {#each row.entries as entry}
              <td
                class="wpef-audit__td-status"
                class:wpef-audit__td-status--allowed={entry.allowed}
                class:wpef-audit__td-status--excluded={!entry.allowed && entry.winningRuleName !== ''}
              >
                <span class="wpef-audit__badge" class:wpef-audit__badge--allowed={entry.allowed} class:wpef-audit__badge--excluded={!entry.allowed && entry.winningRuleName !== ''}>
                  {entry.allowed ? 'Allowed' : entry.winningRuleName ? 'Excluded' : '—'}
                </span>
                {#if entry.winningRuleName}
                  <span class="wpef-audit__rule-name">{entry.winningRuleName}</span>
                {/if}
              </td>
            {/each}
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
</div>

<style>
  .wpef-audit {
    display: flex;
    flex-direction: column;
    gap: var(--wpea-space--md);
  }

  .wpef-audit__description {
    margin: 0;
    color: var(--wpea-surface--text-muted);
    font-size: var(--wpea-text--sm);
  }

  .wpef-audit__table-wrap {
    overflow-x: auto;
  }

  .wpef-audit__table {
    border-collapse: collapse;
    min-width: 100%;
    font-size: var(--wpea-text--sm);
  }

  .wpef-audit__table th,
  .wpef-audit__table td {
    padding: var(--wpea-space--xs) var(--wpea-space--sm);
    text-align: left;
    border-bottom: 1px solid var(--wpea-surface--border);
    white-space: nowrap;
  }

  .wpef-audit__table th {
    font-size: var(--wpea-text--xs);
    font-weight: 600;
    color: var(--wpea-surface--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 2px solid var(--wpea-surface--border);
  }

  .wpef-audit__th-role {
    min-width: 140px;
    position: sticky;
    left: 0;
    background: var(--wpea-surface--bg, #fff);
    z-index: 1;
  }

  .wpef-audit__th-type {
    min-width: 100px;
  }

  .wpef-audit__td-role {
    font-weight: 600;
    color: var(--wpea-surface--text);
    position: sticky;
    left: 0;
    background: var(--wpea-surface--bg, #fff);
    z-index: 1;
  }

  .wpef-audit__td-status {
    vertical-align: top;
  }

  .wpef-audit__badge {
    display: inline-block;
    font-size: var(--wpea-text--xs);
    font-weight: 600;
    padding: 1px 6px;
    border-radius: var(--wpea-radius--s);
    color: var(--wpea-surface--text-muted);
  }

  .wpef-audit__badge--allowed {
    background: var(--wpea-color--success-t-20, rgba(40, 167, 69, 0.15));
    color: var(--wpea-color--success, #28a745);
  }

  .wpef-audit__badge--excluded {
    background: var(--wpea-color--danger-t-20, rgba(220, 53, 69, 0.15));
    color: var(--wpea-color--danger, #dc3545);
  }

  .wpef-audit__rule-name {
    display: inline-block;
    font-size: var(--wpea-text--xs);
    font-weight: 600;
    padding: 1px 6px;
    border-radius: var(--wpea-radius--s);
    background: rgba(0, 0, 0, 0.06);
    color: var(--wpea-surface--text-muted);
    margin-left: 4px;
  }
</style>
