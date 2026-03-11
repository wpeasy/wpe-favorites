export type Rule = {
	id: string;
	name: string;
	roles: string[];
	type: 'include' | 'exclude';
	postTypes: string[];
};

export type RoleOption = {
	value: string;
	label: string;
};

export type PostTypeOption = {
	value: string;
	label: string;
};

export type WpefSettingsData = {
	rules: Rule[];
	roles: RoleOption[];
	postTypes: PostTypeOption[];
	limitsPerType: Record<string, number>;
	maxFavorites: number;
};

export type AuditEntry = {
	postType: string;
	postTypeLabel: string;
	allowed: boolean;
	winningRuleName: string;
};

export type AuditRow = {
	role: string;
	roleLabel: string;
	entries: AuditEntry[];
};

declare global {
	interface Window {
		WPEF_SETTINGS: WpefSettingsData;
	}
}
