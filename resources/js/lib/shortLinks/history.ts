import { shortDate } from '@/lib/datetime';
import type { LinkRevision, MutationChannel, RevisionChange } from '@/types/shortLinks';

export type ChangeDescription = { label: string; before: string; after: string };

type Named = { name?: string };

const ACTIONS: Record<Exclude<LinkRevision['action'], 'restored'>, string> = {
  baseline: 'Recorded as first known state',
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

type RuleSnapshot = Named & {
  is_enabled?: boolean;
  destination_url?: string | null;
  variants?: (Named & { weight?: number })[];
};

function formatRules(value: unknown): string {
  const rules = Array.isArray(value) ? (value as RuleSnapshot[]) : [];
  if (!rules.length) return 'No rules';

  return rules
    .map((rule, index) => {
      const target = rule.variants?.length
        ? rule.variants.map((variant) => `${variant.name} ${variant.weight}`).join(', ')
        : orNone(rule.destination_url);
      return `${index + 1}. ${rule.name}${rule.is_enabled === false ? ' (off)' : ''} → ${target}`;
    })
    .join('; ');
}

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
  routing_rules: { label: 'Smart routing', format: formatRules },
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
  if (change.field === 'password_changed') return { label: 'Password', before: 'Protected', after: 'Replaced' };

  const field = FIELDS[change.field] ?? { label: change.field, format: orNone };

  const before = field.format(change.before);
  const after = field.format(change.after);

  return {
    label: field.label,
    before,
    after:
      before === after && ['destination_url', 'fallback_url', 'routing_rules'].includes(change.field)
        ? `${after} (configuration changed; URL query values are hidden)`
        : after,
  };
}

export function revisionSummary(revision: LinkRevision): string {
  if (revision.action === 'created') return 'Short link created with the settings below.';
  if (revision.action === 'baseline') return 'Settings recorded before the first tracked change.';
  if (revision.action === 'restored')
    return `Settings copied from revision ${revision.restored_from}. Earlier changes are kept.`;
  if (!revision.changes.length) return 'Link settings updated.';

  if (revision.changes.length === 1) {
    const change = revision.changes[0];
    if (change.field === 'is_enabled') return change.after ? 'Link enabled.' : 'Link disabled.';
    if (change.field === 'is_archived') return change.after ? 'Link archived.' : 'Link removed from archive.';
    if (change.field === 'has_password')
      return change.after ? 'Password protection added.' : 'Password protection removed.';
    if (change.field === 'password_changed') return 'Visitor password replaced.';
  }

  const labels = [...new Set(revision.changes.map((change) => describeChange(change).label))];
  const listed =
    labels.length > 2
      ? `${labels.slice(0, 2).join(', ')} and ${labels.length - 2} more setting${labels.length === 3 ? '' : 's'}`
      : labels.join(' and ');
  return `${listed} updated.`;
}
