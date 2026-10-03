import { describe, expect, it, vi } from 'vitest';

import { cycleIndex, groupCommands, queryCommands } from '@/Components/Shell/commandQuery';
import { type CommandActions, type CommandContext, navigationCommands } from '@/Components/Shell/commands';

function fakeActions(): CommandActions {
  return { visit: vi.fn(), switchWorkspace: vi.fn(), shorten: vi.fn() };
}

const baseContext: CommandContext = {
  folders: [{ id: 7, name: 'Campaigns', links_count: 1 }],
  canManage: false,
  isInstanceAdmin: false,
  workspaces: [
    { id: 1, name: 'Current', slug: 'current' },
    { id: 2, name: 'Other', slug: 'other', icon: 'rocket' },
  ],
  currentWorkspaceId: 1,
};

describe('navigationCommands', () => {
  it('lists navigation, folders, personal settings and other workspaces', () => {
    const ids = navigationCommands(baseContext, fakeActions()).map((command) => command.id);

    expect(ids).toEqual(['home', 'links', 'qr', 'analytics', 'folder-7', 'profile', 'security', 'tokens', 'ws-2']);
  });

  it('adds workspace and instance settings when permitted', () => {
    const ids = navigationCommands({ ...baseContext, canManage: true, isInstanceAdmin: true }, fakeActions()).map(
      (command) => command.id,
    );

    expect(ids).toEqual([
      'home',
      'links',
      'qr',
      'analytics',
      'folder-7',
      'ws-settings',
      'domains',
      'members',
      'profile',
      'security',
      'tokens',
      'instance',
      'ws-2',
    ]);
  });

  it('wires commands to actions', () => {
    const actions = fakeActions();
    const commands = navigationCommands(baseContext, actions);

    commands.find((command) => command.id === 'folder-7')?.run();
    commands.find((command) => command.id === 'ws-2')?.run();

    expect(commands.find((command) => command.id === 'folder-7')?.hint).toBe('1 link');
    expect(actions.visit).toHaveBeenCalledWith('links.index', { folder: 7 });
    expect(actions.switchWorkspace).toHaveBeenCalledWith(2);
  });
});

describe('queryCommands', () => {
  const available = navigationCommands(baseContext, fakeActions());

  it('returns everything for an empty query', () => {
    expect(queryCommands('  ', true, available, fakeActions())).toEqual(available);
  });

  it('offers to shorten pasted URLs for editors', () => {
    const actions = fakeActions();
    const [first] = queryCommands('https://example.com/a', true, available, actions);

    first?.run();

    expect(first?.id).toBe('shorten');
    expect(actions.shorten).toHaveBeenCalledWith('https://example.com/a');
    expect(queryCommands('https://example.com/a', false, available, actions)[0]?.id).not.toBe('shorten');
  });

  it('offers a link search and filters by label, group and keywords', () => {
    const ids = queryCommands('Security', true, available, fakeActions()).map((command) => command.id);

    expect(ids).toEqual(['search-links', 'security']);
    expect(queryCommands('2fa', false, available, fakeActions()).map((command) => command.id)).toEqual(['security']);
  });
});

describe('groupCommands', () => {
  it('groups commands in first-seen order while keeping flat indexes', () => {
    const groups = groupCommands(navigationCommands(baseContext, fakeActions()));

    expect(groups.map(([group]) => group)).toEqual(['Go to', 'Folders', 'Settings', 'Switch workspace']);
    expect(groups[3]?.[1][0]?.index).toBe(8);
  });
});

describe('cycleIndex', () => {
  it('wraps around in both directions', () => {
    expect(cycleIndex(0, -1, 3)).toBe(2);
    expect(cycleIndex(2, 1, 3)).toBe(0);
    expect(cycleIndex(0, 1, 0)).toBe(0);
  });
});
