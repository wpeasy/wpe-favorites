import type { Snippet } from 'svelte';
import type { StringOrSnippet } from '../types';

/**
 * Type guard to check if a value is a Snippet (function)
 * In Svelte 5, Snippets are functions
 */
export function isSnippet(value: StringOrSnippet | undefined | null): value is Snippet {
	return typeof value === 'function';
}
