import type { SelectOption } from '@/lib/controls';
import type { LinkFilters } from '@/Pages/Links/types';

export const LINK_CSV_COLUMNS = [
  { key: 'destination_url', label: 'Destination URL' },
  { key: 'domain', label: 'Domain' },
  { key: 'slug', label: 'Slug' },
  { key: 'fallback_url', label: 'Fallback URL' },
  { key: 'folder', label: 'Folder' },
  { key: 'tags', label: 'Tags' },
  { key: 'enabled', label: 'Enabled' },
  { key: 'archived', label: 'Archived' },
  { key: 'activates_at', label: 'Activates at' },
  { key: 'expires_at', label: 'Expires at' },
  { key: 'visit_limit', label: 'Visit limit' },
  { key: 'format_version', label: 'Format version' },
] as const;

type LinkCsvColumn = (typeof LINK_CSV_COLUMNS)[number]['key'];

export type ColumnMapping = Partial<Record<LinkCsvColumn, number>>;

export type ImportRow = {
  row: number;
  status: 'ready' | 'invalid' | 'created' | 'skipped';
  domain: string | null;
  slug: string | null;
  short_url: string | null;
  destination_url: string;
  errors: string[];
};

export type PreviewSummary = {
  total: number;
  ready: number;
  invalid: number;
  new_folders: string[];
  new_tags: string[];
};

export type ImportPreview = { headers: string[]; mapping: ColumnMapping; rows: ImportRow[]; summary: PreviewSummary };

export type ResultSummary = { total: number; created: number; skipped: number };

export type ImportResult = { rows: ImportRow[]; summary: ResultSummary };

function plural(count: number, word: string): string {
  return `${count.toLocaleString()} ${word}${count === 1 ? '' : 's'}`;
}

export function exportQuery(filters: LinkFilters): Record<string, string> {
  return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
}

export function mappingOptions(headers: string[]): SelectOption[] {
  return [
    { value: '', label: 'Don’t import' },
    ...headers.map((header, index) => ({ value: String(index), label: header || `Column ${index + 1}` })),
  ];
}

export function mappingSelection(mapping: ColumnMapping): Record<string, string> {
  return Object.fromEntries(
    LINK_CSV_COLUMNS.map(({ key }) => [key, mapping[key] === undefined ? '' : String(mapping[key])]),
  );
}

export function mappingPayload(selection: Record<string, string>): ColumnMapping {
  return Object.fromEntries(
    Object.entries(selection)
      .filter(([, index]) => index !== '')
      .map(([column, index]) => [column, Number(index)]),
  );
}

export function previewHeadline(summary: PreviewSummary): string {
  const ready = `${summary.ready.toLocaleString()} of ${plural(summary.total, 'link')} ${summary.ready === 1 ? 'is' : 'are'} ready to import`;

  return summary.invalid > 0 ? `${ready} · ${summary.invalid.toLocaleString()} will be skipped` : ready;
}

export function resultHeadline(summary: ResultSummary): string {
  const created = `Imported ${plural(summary.created, 'link')}`;

  return summary.skipped > 0 ? `${created} · ${summary.skipped.toLocaleString()} skipped` : created;
}
