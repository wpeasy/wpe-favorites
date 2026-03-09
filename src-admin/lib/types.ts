import type { Snippet } from 'svelte';

export type Size = 'xs' | 's' | 'm' | 'l' | 'xl' | '2xl';
export type ColorVariant = 'primary' | 'secondary' | 'neutral' | 'success' | 'warning' | 'danger' | 'info';
export type ButtonVariant = 'primary' | 'secondary' | 'ghost';

// Union type for flexible string or Snippet content
export type StringOrSnippet = string | Snippet;

// Option types for form controls
export type SelectOption = {
	value: string;
	label: StringOrSnippet;
};

export type SelectOptionWithDisabled = SelectOption & {
	disabled?: boolean;
};

export type AdvancedSelectOption = SelectOptionWithDisabled & {
	deletable?: boolean;
	locked?: boolean;
};

export type RadioOption = SelectOptionWithDisabled;

export type ToggleOption = {
	value: string;
	label: StringOrSnippet;
	icon?: Snippet;
};

// Tab/Accordion item types
export type TabItem = {
	id: string;
	label: StringOrSnippet;
	content: Snippet;
};

export type AccordionItem = {
	id: string;
	title: StringOrSnippet;
	content: Snippet;
};

// Table column type
// Note: render returns any for flexibility - can return Snippet or direct values
export type TableColumn<T = Record<string, unknown>> = {
	key: string;
	label: StringOrSnippet;
	render?: (row: T) => any;
};

// Toast item type
export type ToastItem = {
	id: string;
	title?: StringOrSnippet;
	message: StringOrSnippet;
	variant?: ColorVariant;
	duration?: number;
};
