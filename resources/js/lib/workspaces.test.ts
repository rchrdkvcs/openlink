import { Rocket } from '@lucide/vue';
import { describe, expect, it } from 'vitest';

import { WORKSPACE_ICON_CATEGORIES, WORKSPACE_ICONS, workspaceIconComponent, workspaceInitial } from '@/lib/workspaces';

describe('workspace icons', () => {
  it('keeps the category order', () => {
    expect(WORKSPACE_ICON_CATEGORIES.map((category) => category.label)).toEqual([
      'Work',
      'Growth',
      'People',
      'Commerce',
      'Tech',
      'Nature',
      'Food & travel',
      'Creative & more',
    ]);
  });

  it('indexes every icon by key', () => {
    expect(Object.keys(WORKSPACE_ICONS)).toHaveLength(124);
    expect(workspaceIconComponent('rocket')).toBe(Rocket);
  });

  it('returns null for missing keys', () => {
    expect(workspaceIconComponent(null)).toBeNull();
    expect(workspaceIconComponent('unknown')).toBeNull();
  });
});

describe('workspaceInitial', () => {
  it('uppercases the first character', () => {
    expect(workspaceInitial('acme')).toBe('A');
    expect(workspaceInitial(null)).toBe('O');
  });
});
