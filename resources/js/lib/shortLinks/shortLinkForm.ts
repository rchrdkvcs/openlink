import { normalizeUrl } from '@/lib/links';
import { cloneRules, isRoutingErrorKey } from '@/lib/routing';
import type { Domain } from '@/types/payloads';
import type { RoutingRuleDraft, ShortLink } from '@/types/shortLinks';

export const PASSWORD_MASK = '********';

export type ShortLinkForm = {
  folder_id: string;
  domain_id: number | string;
  slug: string;
  destination_url: string;
  fallback_url: string;
  is_enabled: boolean;
  activates_at: string;
  expires_at: string;
  visit_limit: string;
  password: string;
  tags: string;
  routing_rules: RoutingRuleDraft[];
};

export type QuickLinkForm = Pick<ShortLinkForm, 'destination_url' | 'domain_id' | 'folder_id' | 'slug' | 'is_enabled'>;

export type InspectorSection = 'schedule' | 'limit' | 'password' | 'fallback' | 'routing';

export type PayloadOptions = { hasPassword?: boolean };

const SECTION_BY_FIELD: Record<string, InspectorSection> = {
  activates_at: 'schedule',
  expires_at: 'schedule',
  visit_limit: 'limit',
  password: 'password',
  fallback_url: 'fallback',
};

export function folderField(folderId: number | null | undefined): string {
  return folderId ? String(folderId) : '';
}

function dateField(value: string | null | undefined): string {
  return value ? String(value).slice(0, 16) : '';
}

export function selectableDomains<T extends Pick<Domain, 'status'>>(domains: T[]): T[] {
  return domains.filter((domain) => domain.status === 'active');
}

export function initialDomainId(domains: Pick<Domain, 'id'>[], preferredId: number | null | undefined): number | '' {
  return domains.find((domain) => domain.id === preferredId)?.id ?? domains[0]?.id ?? '';
}

export function newQuickLinkForm(domainId: number | '', folderId: number | null | undefined): QuickLinkForm {
  return { destination_url: '', domain_id: domainId, folder_id: folderField(folderId), slug: '', is_enabled: true };
}

export function toForm(link: ShortLink): ShortLinkForm {
  return {
    folder_id: folderField(link.folder?.id),
    domain_id: link.domain.id,
    slug: link.slug,
    destination_url: link.destination_url,
    fallback_url: link.fallback_url ?? '',
    is_enabled: link.is_enabled,
    activates_at: dateField(link.activates_at),
    expires_at: dateField(link.expires_at),
    visit_limit: link.visit_limit ? String(link.visit_limit) : '',
    password: link.has_password ? PASSWORD_MASK : '',
    tags: link.tags.map((tag) => tag.name).join(', '),
    routing_rules: cloneRules(link.routing_rules ?? []),
  };
}

export function toPayload<T extends { destination_url: string; password?: string }>(
  form: T,
  options: PayloadOptions = {},
): T | Omit<T, 'password'> {
  const payload = { ...form, destination_url: normalizeUrl(form.destination_url) };
  if (options.hasPassword && form.password === PASSWORD_MASK) {
    const { password: _password, ...rest } = payload;
    return rest;
  }

  return payload;
}

export function closedSections(): Record<InspectorSection, boolean> {
  return { schedule: false, limit: false, password: false, fallback: false, routing: false };
}

export function sectionFor(errorKey: string): InspectorSection | null {
  if (isRoutingErrorKey(errorKey)) return 'routing';

  return SECTION_BY_FIELD[errorKey] ?? null;
}

export function sectionsWithErrors(errors: Partial<Record<string, string | undefined>>): InspectorSection[] {
  const sections = Object.keys(errors)
    .map(sectionFor)
    .filter((section): section is InspectorSection => section !== null);

  return [...new Set(sections)];
}
