import { shortDate } from '@/lib/datetime';
import type { LinkRevision, MutationChannel, RevisionChange } from '@/types/shortLinks';

export type ChangeDescription = { label: string; before: string; after: string };

type Named = { name?: string };

const ACTIONS: Record<Exclude<LinkRevision['action'], 'restored'>, string> = {
  created: 'Created',
  updated: 'Edited',
  moved: 'Moved',
  archived: 'Archived',
};

const CHANNELS: Record<MutationChannel, string> = {
  dashboard: 'Dashboard',
  api: 'API',
  system: 'System',
};

const orNone = (value: unknown) => (value === null || value === undefined || value === '' ? 'None' : String(value));

const names = (value: unknown): string[] => (Array.isArray(value) ? value.map((item: Named) => item.name ?? '') : []);

const FIELDS: Record<string, { label: string; format: (value: unknown) => string }> = {
  short_url: { label: 'Short URL', format: orNone },
  destination_url: { label: 'Destination', format: orNone },
  fallback_url: { label: 'Fallback', format: orNone },
  is_enabled: { label: 'State', format: (value) => (value ? 'Enabled' : 'Disabled') },
  is_archived: { label: 'Archive', format: (value) => (value ? 'Archived' : 'Not archived') },
  activates_at: { label: 'Starts', format: (value) => shortDate(value as string) || 'Immediately' },
  expires_at: { label: 'Ends', format: (value) => shortDate(value as string) || 'Never' },
  visit_limit: { label: 'Visit limit', format: (value) => (value ? String(value) : 'Unlimited') },
  has_password: { label: 'Password', format: (value) => (value ? 'Protected' : 'Not protected') },
  folder: { label: 'Folder', format: (value) => (value as Named | null)?.name ?? 'No folder' },
  tags: { label: 'Tags', format: (value) => names(value).join(', ') || 'No tags' },
  routing_rules: {
    label: 'Smart routing',
    format: (value) => {
      const rules = names(value);
      if (!rules.length) return 'No rules';
      return `${rules.length} ${rules.length === 1 ? 'rule' : 'rules'}: ${rules.join(', ')}`;
    },
  },
};

export function actorLabel(revision: LinkRevision): string {
  if (revision.actor) return revision.actor.name;

  return revision.channel === 'system' ? 'System' : 'Deleted member';
}

export function actionLabel(revision: LinkRevision): string {
  return revision.action === 'restored' ? `Restored revision ${revision.restored_from}` : ACTIONS[revision.action];
}

export function channelLabel(channel: MutationChannel): string {
  return CHANNELS[channel];
}

export function describeChange(change: RevisionChange): ChangeDescription {
  const field = FIELDS[change.field] ?? { label: change.field, format: orNone };

  return { label: field.label, before: field.format(change.before), after: field.format(change.after) };
}
