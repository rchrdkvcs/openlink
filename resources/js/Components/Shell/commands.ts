import {
  BarChart3,
  Building2,
  Folder,
  Globe2,
  Home,
  KeyRound,
  Link2,
  QrCode,
  Server,
  ShieldCheck,
  User,
  Users,
} from '@lucide/vue';
import type { Component } from 'vue';

import { linkCountLabel } from '@/Components/Shell/folders';
import type { NavigationFolder } from '@/types';
import type { WorkspaceSummary } from '@/types/payloads';

export type Command = {
  id: string;
  group: string;
  label: string;
  hint?: string;
  icon?: Component;
  workspace?: Pick<WorkspaceSummary, 'name' | 'icon'>;
  keywords?: string;
  run: () => void;
};

export type CommandActions = {
  visit: (name: string, params?: Record<string, unknown>) => void;
  switchWorkspace: (workspaceId: number) => void;
  shorten: (url: string) => void;
};

export type CommandContext = {
  folders: NavigationFolder[];
  canManage: boolean;
  isInstanceAdmin: boolean;
  workspaces: WorkspaceSummary[];
  currentWorkspaceId?: number;
};

type SettingsCommand = Omit<Command, 'group'>;

function settings(commands: SettingsCommand[]): Command[] {
  return commands.map((command) => ({ ...command, group: 'Settings' }));
}

export function navigationCommands(context: CommandContext, actions: CommandActions): Command[] {
  const { visit } = actions;

  return [
    { id: 'home', group: 'Go to', label: 'Home', icon: Home, run: () => visit('dashboard') },
    { id: 'links', group: 'Go to', label: 'All links', icon: Link2, run: () => visit('links.index') },
    { id: 'qr', group: 'Go to', label: 'QR codes', icon: QrCode, run: () => visit('qr-codes.index') },
    { id: 'analytics', group: 'Go to', label: 'Analytics', icon: BarChart3, run: () => visit('analytics.index') },
    ...context.folders.map((folder) => ({
      id: `folder-${folder.id}`,
      group: 'Folders',
      label: folder.name,
      hint: linkCountLabel(folder.links_count),
      icon: Folder,
      run: () => visit('links.index', { folder: folder.id }),
    })),
    ...settings(
      context.canManage
        ? [
            {
              id: 'ws-settings',
              label: 'Workspace settings',
              icon: Building2,
              keywords: 'name icon color preferred domain',
              run: () => visit('settings.workspace'),
            },
            {
              id: 'domains',
              label: 'Domains',
              icon: Globe2,
              keywords: 'dns hostname',
              run: () => visit('domains.index'),
            },
            {
              id: 'members',
              label: 'Members',
              icon: Users,
              keywords: 'invite team people roles',
              run: () => visit('members.index'),
            },
          ]
        : [],
    ),
    ...settings([
      { id: 'profile', label: 'Profile', icon: User, run: () => visit('profile.edit', { tab: 'profile' }) },
      {
        id: 'security',
        label: 'Security',
        icon: ShieldCheck,
        keywords: 'password two-factor 2fa',
        run: () => visit('profile.edit', { tab: 'security' }),
      },
      { id: 'tokens', label: 'API tokens', icon: KeyRound, run: () => visit('profile.edit', { tab: 'api-tokens' }) },
    ]),
    ...settings(
      context.isInstanceAdmin
        ? [
            {
              id: 'instance',
              label: 'Instance settings',
              icon: Server,
              keywords: 'registration updates reserved slugs',
              run: () => visit('settings.index'),
            },
          ]
        : [],
    ),
    ...context.workspaces
      .filter((item) => item.id !== context.currentWorkspaceId)
      .map((item) => ({
        id: `ws-${item.id}`,
        group: 'Switch workspace',
        label: item.name,
        workspace: item,
        run: () => actions.switchWorkspace(item.id),
      })),
  ];
}
