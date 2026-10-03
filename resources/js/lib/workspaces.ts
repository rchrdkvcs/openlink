import type { Component } from 'vue';

import { leisureIconCategories } from '@/lib/workspaceIconsLeisure';
import { workIconCategories } from '@/lib/workspaceIconsWork';
import { worldIconCategories } from '@/lib/workspaceIconsWorld';

export type WorkspaceAppearance = {
  name: string;
  icon?: string | null;
  color?: string | null;
};

export type WorkspaceIconCategory = { label: string; icons: Record<string, Component> };

export const WORKSPACE_ICON_CATEGORIES: WorkspaceIconCategory[] = [
  ...workIconCategories,
  ...worldIconCategories,
  ...leisureIconCategories,
];

export const WORKSPACE_ICONS: Record<string, Component> = Object.assign(
  {},
  ...WORKSPACE_ICON_CATEGORIES.map((category) => category.icons),
);

export function workspaceIconComponent(key?: string | null): Component | null {
  return key ? (WORKSPACE_ICONS[key] ?? null) : null;
}

export function workspaceInitial(name?: string | null): string {
  return String(name ?? 'O')
    .slice(0, 1)
    .toUpperCase();
}
