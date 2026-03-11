import type { Rule, RoleOption, PostTypeOption, AuditRow, AuditEntry } from './types';

function generateId(): string {
	return Math.random().toString(36).slice(2, 9);
}

const data = window.WPEF_SETTINGS;

export const roleOptions: RoleOption[] = [
	{ value: 'all', label: 'All Roles' },
	...data.roles,
];

export const postTypeOptions: PostTypeOption[] = data.postTypes;

function createDefaultRule(): Rule {
	return {
		id: generateId(),
		name: '',
		roles: ['all'],
		type: 'include',
		postTypes: [],
	};
}

// Initialize rules from server data or create default.
const initialRules: Rule[] =
	data.rules.length > 0
		? data.rules.map((r) => ({
				...r,
				id: r.id || generateId(),
				name: r.name || '',
			}))
		: [{ ...createDefaultRule(), name: 'Default' }];

export let rules = $state<Rule[]>(initialRules);

// Track which accordion panel is open. Wrapped in object to avoid reassignment export error.
const accordion = $state({ openId: initialRules[0]?.id ?? null as string | null });

export function getOpenRuleId(): string | null {
	return accordion.openId;
}

// Limits state. Wrapped in object — Svelte 5 cannot export reassigned $state from modules.
const limits = $state({
	perType: { ...data.limitsPerType } as Record<string, number>,
	max: data.maxFavorites,
});

export function getRulesJson(): string {
	return JSON.stringify(rules);
}

export function getLimitsJson(): string {
	return JSON.stringify(limits.perType);
}

export function getMaxFavorites(): number {
	return limits.max;
}

export function getLimitForType(slug: string): number {
	return limits.perType[slug] ?? 0;
}

export function setLimitForType(slug: string, value: number): void {
	if (value > 0) {
		limits.perType[slug] = value;
	} else {
		delete limits.perType[slug];
	}
}

export function setMaxFavorites(value: number): void {
	limits.max = value;
}

export function addRule(): void {
	const rule = createDefaultRule();
	rules.push(rule);
	accordion.openId = rule.id;
}

export function removeRule(id: string): void {
	if (rules.length <= 1) return;
	const index = rules.findIndex((r) => r.id === id);
	if (index <= 0) return; // Cannot delete default rule (index 0).

	rules.splice(index, 1);

	// If the removed rule was open, open the nearest sibling.
	if (accordion.openId === id) {
		const next = rules[Math.min(index, rules.length - 1)];
		accordion.openId = next?.id ?? null;
	}
}

export function updateRule(id: string, updates: Partial<Rule>): void {
	const rule = rules.find((r) => r.id === id);
	if (rule) {
		Object.assign(rule, updates);
	}
}

export function toggleRule(id: string): void {
	accordion.openId = accordion.openId === id ? null : id;
}

export function reorderRules(fromIndex: number, toIndex: number): void {
	if (fromIndex === toIndex) return;
	if (fromIndex < 0 || toIndex < 0) return;
	if (fromIndex >= rules.length || toIndex >= rules.length) return;
	// Default rule (index 0) cannot be moved, and nothing can be dragged above it.
	if (fromIndex === 0 || toIndex === 0) return;

	const [moved] = rules.splice(fromIndex, 1);
	rules.splice(toIndex, 0, moved);
}

/**
 * Resolve rules for a specific role, returning per-post-type audit data.
 * Mirrors the PHP resolution algorithm in Settings::get_enabled_post_types_for_user().
 */
function resolveForRole(roleSlug: string): AuditEntry[] {
	// Track: per post type, whether it's included and which rule decided it.
	const state = new Map<string, { allowed: boolean; ruleName: string }>();

	// Start with all post types allowed by default.
	for (const pt of postTypeOptions) {
		state.set(pt.value, { allowed: true, ruleName: '' });
	}

	// Process rules top-to-bottom.
	for (let i = 0; i < rules.length; i++) {
		const rule = rules[i];

		// Does this role match?
		const matches =
			rule.roles.includes('all') || rule.roles.includes(roleSlug);
		if (!matches) continue;

		const ruleName = rule.name || `Rule ${i + 1}`;

		for (const pt of rule.postTypes) {
			if (!state.has(pt)) continue;

			if (rule.type === 'include') {
				state.set(pt, { allowed: true, ruleName });
			} else {
				state.set(pt, { allowed: false, ruleName });
			}
		}
	}

	return postTypeOptions.map((pt) => {
		const s = state.get(pt.value)!;
		return {
			postType: pt.value,
			postTypeLabel: pt.label,
			allowed: s.allowed,
			winningRuleName: s.ruleName,
		};
	});
}

/**
 * Build the full audit table — one row per role (excluding "all").
 * Reactive: recalculates whenever rules change.
 */
export function getAuditRows(): AuditRow[] {
	// All concrete roles (skip "all" — it's implicit in rule matching).
	const concreteRoles = roleOptions.filter((r) => r.value !== 'all');

	// Also include an "Anonymous" row — matches only 'all' rules.
	const rows: AuditRow[] = [
		{
			role: '_anonymous',
			roleLabel: 'Anonymous (logged out)',
			entries: resolveForRole('_anonymous'),
		},
	];

	for (const role of concreteRoles) {
		rows.push({
			role: role.value,
			roleLabel: role.label,
			entries: resolveForRole(role.value),
		});
	}

	return rows;
}
