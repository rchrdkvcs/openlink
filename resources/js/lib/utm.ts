export const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as const;

export type UtmKey = (typeof UTM_KEYS)[number];

export type UtmValues = Record<UtmKey, string>;

type UrlParts = { base: string; query: string | null; fragment: string };

function isUtmKey(key: string): key is UtmKey {
  return (UTM_KEYS as readonly string[]).includes(key);
}

function decode(value: string): string {
  const spaced = value.replace(/\+/g, ' ');
  try {
    return decodeURIComponent(spaced);
  } catch {
    return spaced;
  }
}

function splitUrl(url: string): UrlParts {
  const hashAt = url.indexOf('#');
  const beforeHash = hashAt === -1 ? url : url.slice(0, hashAt);
  const fragment = hashAt === -1 ? '' : url.slice(hashAt);
  const queryAt = beforeHash.indexOf('?');

  return queryAt === -1
    ? { base: beforeHash, query: null, fragment }
    : { base: beforeHash.slice(0, queryAt), query: beforeHash.slice(queryAt + 1), fragment };
}

function segmentKey(segment: string): string {
  const equalsAt = segment.indexOf('=');

  return decode(equalsAt === -1 ? segment : segment.slice(0, equalsAt));
}

function segmentValue(segment: string): string {
  const equalsAt = segment.indexOf('=');

  return equalsAt === -1 ? '' : decode(segment.slice(equalsAt + 1));
}

export function emptyUtm(): UtmValues {
  return { utm_source: '', utm_medium: '', utm_campaign: '', utm_term: '', utm_content: '' };
}

export function readUtm(url: string): UtmValues {
  const values = emptyUtm();
  const seen = new Set<UtmKey>();
  const { query } = splitUrl(url);

  for (const segment of query?.split('&') ?? []) {
    const key = segmentKey(segment);
    if (!isUtmKey(key) || seen.has(key)) continue;
    seen.add(key);
    values[key] = segmentValue(segment);
  }

  return values;
}

export function hasUrlBase(url: string): boolean {
  return splitUrl(url).base.trim() !== '';
}

export function writeUtm(url: string, values: Partial<UtmValues>): string {
  const parts = splitUrl(url);
  if (parts.base.trim() === '') return url;
  let segments = parts.query ? parts.query.split('&') : [];
  const additions: string[] = [];
  let changed = false;

  for (const key of UTM_KEYS) {
    const desired = values[key];
    if (desired === undefined) continue;

    const positions = segments.flatMap((segment, index) => (segmentKey(segment) === key ? [index] : []));
    if (desired === '' && positions.length === 0) continue;
    if (desired !== '' && positions.length === 1 && segmentValue(segments[positions[0]]) === desired) continue;

    changed = true;
    const replacement = `${key}=${encodeURIComponent(desired)}`;
    const [first, ...duplicates] = positions;

    if (desired !== '' && first === undefined) additions.push(replacement);
    if (desired !== '' && first !== undefined) segments[first] = replacement;

    const removed = desired === '' ? positions : duplicates;
    segments = segments.filter((_, index) => !removed.includes(index));
  }

  if (!changed) return url;

  const kept = segments.join('&');
  const separator = kept === '' || kept.endsWith('&') ? '' : '&';
  const query = additions.length ? `${kept}${separator}${additions.join('&')}` : kept;

  return `${parts.base}${query ? `?${query}` : ''}${parts.fragment}`;
}

export type UtmTemplateValues = Record<UtmKey, string | null>;

export type UtmTemplate = UtmTemplateValues & { id: number; name: string };

export type UtmChange = { key: UtmKey; from: string; to: string };

function definedValues(template: UtmTemplateValues): Partial<UtmValues> {
  return Object.fromEntries(UTM_KEYS.flatMap((key) => (template[key] ? [[key, template[key]]] : [])));
}

export function templateChanges(url: string, template: UtmTemplateValues): UtmChange[] {
  const current = readUtm(url);

  return Object.entries(definedValues(template))
    .filter(([key, value]) => current[key as UtmKey] !== value)
    .map(([key, value]) => ({ key: key as UtmKey, from: current[key as UtmKey], to: value }));
}

export function applyTemplate(url: string, template: UtmTemplateValues): string {
  return writeUtm(url, definedValues(template));
}

export const UTM_FIELDS: { key: UtmKey; label: string; placeholder: string }[] = [
  { key: 'utm_source', label: 'Source', placeholder: 'newsletter' },
  { key: 'utm_medium', label: 'Medium', placeholder: 'email' },
  { key: 'utm_campaign', label: 'Campaign', placeholder: 'spring_sale' },
  { key: 'utm_term', label: 'Term', placeholder: 'running shoes' },
  { key: 'utm_content', label: 'Content', placeholder: 'header_button' },
];

export type UtmTemplateForm = UtmValues & { name: string };

export function templateForm(template: UtmTemplate | null): UtmTemplateForm {
  const values = emptyUtm();
  for (const key of UTM_KEYS) values[key] = template?.[key] ?? '';

  return { name: template?.name ?? '', ...values };
}

export function templateSummary(template: UtmTemplateValues): string {
  return UTM_KEYS.flatMap((key) => (template[key] ? [`${key}=${template[key]}`] : [])).join(' · ');
}
