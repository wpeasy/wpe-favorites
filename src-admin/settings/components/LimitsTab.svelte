<script lang="ts">
  import { postTypeOptions, setLimitForType, setMaxFavorites, getMaxFavorites, getLimitForType } from '../state.svelte';

  function handleLimitChange(slug: string, event: Event): void {
    const target = event.target as HTMLInputElement;
    setLimitForType(slug, parseInt(target.value, 10) || 0);
  }

  function handleMaxChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    setMaxFavorites(parseInt(target.value, 10) || 0);
  }
</script>

<div class="wpef-limits">
  <div class="wpef-limits__section">
    <h3 class="wpef-limits__heading">Per-Type Limits</h3>
    <p class="wpef-limits__description">
      Maximum favorites per post type, regardless of rules. Leave empty for unlimited.
    </p>
    <table class="wpef-limits__table">
      <thead>
        <tr>
          <th>Post Type</th>
          <th class="wpef-limits__table-num">Max per User</th>
        </tr>
      </thead>
      <tbody>
        {#each postTypeOptions as pt}
          <tr>
            <td>
              <span class="wpef-limits__type-label">{pt.label}</span>
            </td>
            <td class="wpef-limits__table-num">
              <input
                type="number"
                class="wpef-limits__input"
                value={getLimitForType(pt.value) || ''}
                oninput={(e) => handleLimitChange(pt.value, e)}
                min="1"
                placeholder="Unlimited"
              />
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>

  <div class="wpef-limits__section">
    <h3 class="wpef-limits__heading">Global Limit</h3>
    <p class="wpef-limits__description">
      Maximum total favorites across all post types. Leave empty for unlimited.
    </p>
    <input
      type="number"
      class="wpef-limits__input wpef-limits__input--global"
      value={getMaxFavorites() || ''}
      oninput={handleMaxChange}
      min="1"
      placeholder="Unlimited"
    />
  </div>
</div>

<style>
  .wpef-limits {
    display: flex;
    flex-direction: column;
    gap: var(--wpea-space--lg);
  }

  .wpef-limits__section {
    display: flex;
    flex-direction: column;
    gap: var(--wpea-space--sm);
  }

  .wpef-limits__heading {
    margin: 0;
    font-size: var(--wpea-text--md);
    font-weight: 600;
  }

  .wpef-limits__description {
    margin: 0;
    color: var(--wpea-surface--text-muted);
    font-size: var(--wpea-text--sm);
  }

  .wpef-limits__table {
    border-collapse: collapse;
    max-width: 400px;
  }

  .wpef-limits__table th,
  .wpef-limits__table td {
    padding: var(--wpea-space--xs) var(--wpea-space--sm);
    text-align: left;
    border-bottom: 1px solid var(--wpea-surface--border);
  }

  .wpef-limits__table th {
    font-size: var(--wpea-text--xs);
    font-weight: 600;
    color: var(--wpea-surface--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }

  .wpef-limits__table-num {
    width: 120px;
  }

  .wpef-limits__type-label {
    font-size: var(--wpea-text--sm);
  }

  .wpef-limits__input {
    width: 100%;
    padding: 4px var(--wpea-space--xs);
    font-size: var(--wpea-text--sm);
    border: 1px solid var(--wpea-surface--border);
    border-radius: var(--wpea-radius--s);
    background: var(--wpea-surface--bg);
    color: var(--wpea-surface--text);
    outline: none;
  }

  .wpef-limits__input:focus {
    border-color: var(--wpea-color--primary);
  }

  .wpef-limits__input--global {
    max-width: 120px;
  }
</style>
