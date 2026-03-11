<script lang="ts">
  import { rules, addRule, reorderRules } from '../state.svelte';
  import { Button } from '../../lib';
  import RuleRow from './RuleRow.svelte';

  let dragFromIndex = $state<number | null>(null);
  let dragOverIndex = $state<number | null>(null);

  function handleDragStart(index: number): void {
    // Default rule (index 0) cannot be dragged.
    if (index === 0) return;
    dragFromIndex = index;
  }

  function handleDragOver(event: DragEvent, index: number): void {
    // Cannot drop onto the default rule slot (index 0).
    if (index === 0) return;
    if (dragFromIndex === null) return;

    event.preventDefault();
    if (event.dataTransfer) {
      event.dataTransfer.dropEffect = 'move';
    }
    dragOverIndex = index;
  }

  function handleDrop(index: number): void {
    if (index === 0) return;
    if (dragFromIndex !== null && dragFromIndex !== index) {
      reorderRules(dragFromIndex, index);
    }
    dragFromIndex = null;
    dragOverIndex = null;
  }

  function handleDragEnd(): void {
    dragFromIndex = null;
    dragOverIndex = null;
  }
</script>

<div class="wpef-rules-repeater">
  <div class="wpef-rules-repeater__list">
    {#each rules as rule, index (rule.id)}
      <div
        class="wpef-rules-repeater__item"
        class:wpef-rules-repeater__item--drag-over={dragOverIndex === index && dragFromIndex !== index}
        ondragover={(e) => handleDragOver(e, index)}
        ondrop={() => handleDrop(index)}
        role="listitem"
      >
        <RuleRow
          {rule}
          {index}
          isDefault={index === 0}
          onDragStart={() => handleDragStart(index)}
          onDragEnd={handleDragEnd}
        />
      </div>
    {/each}
  </div>

  <div class="wpef-rules-repeater__footer">
    <Button variant="secondary" onclick={addRule}>+ Add Rule</Button>
  </div>
</div>

<style>
  .wpef-rules-repeater__list {
    display: flex;
    flex-direction: column;
    gap: var(--wpea-space--xs);
  }

  .wpef-rules-repeater__item {
    border-radius: var(--wpea-radius--m);
    transition: outline 0.15s ease;
    outline: 2px solid transparent;
  }

  .wpef-rules-repeater__item--drag-over {
    outline: 2px dashed var(--wpea-color--primary);
  }

  .wpef-rules-repeater__footer {
    margin-top: var(--wpea-space--md);
  }
</style>
