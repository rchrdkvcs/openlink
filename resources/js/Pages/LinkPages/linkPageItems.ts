import type { LinkPageDisplay, PublicLinkPage } from '@/Components/LinkPages/types';
import { displayUrl } from '@/lib/links';

import type { LinkPageAvailability, LinkPageItem, LinkPageShortLinkOption } from './types';

export const MAX_ITEMS = 50;

export type EditableItem = {
  short_link_id: number;
  label: string;
  short_url: string;
  destination_url: string;
  availability: LinkPageAvailability;
  icon: string | null;
};

const AVAILABILITY_LABELS: Record<LinkPageAvailability, string> = {
  active: 'Visible',
  scheduled: 'Hidden until scheduled',
  expired: 'Hidden: expired',
  disabled: 'Hidden: disabled',
  archived: 'Hidden: archived',
  domain_unavailable: 'Hidden: domain unavailable',
};

export function availabilityLabel(availability: LinkPageAvailability): string {
  return AVAILABILITY_LABELS[availability];
}

export function toEditable(items: LinkPageItem[]): EditableItem[] {
  return items.map((item) => ({
    short_link_id: item.short_link_id,
    label: item.label ?? '',
    short_url: item.short_url,
    destination_url: item.destination_url,
    availability: item.availability,
    icon: item.icon,
  }));
}

export function addItem(items: EditableItem[], link: LinkPageShortLinkOption): EditableItem[] {
  if (items.length >= MAX_ITEMS || items.some((item) => item.short_link_id === link.id)) return items;

  return [
    ...items,
    {
      short_link_id: link.id,
      label: '',
      short_url: link.short_url,
      destination_url: link.destination_url,
      availability: link.availability,
      icon: link.icon,
    },
  ];
}

export function moveItem(items: EditableItem[], index: number, offset: -1 | 1): EditableItem[] {
  const target = index + offset;
  if (index < 0 || index >= items.length || target < 0 || target >= items.length) return items;

  const next = [...items];
  [next[index], next[target]] = [next[target]!, next[index]!];

  return next;
}

export function removeItem(items: EditableItem[], index: number): EditableItem[] {
  return items.filter((_, position) => position !== index);
}

export function itemLabel(item: EditableItem): string {
  return item.label.trim() || displayUrl(item.short_url);
}

export function toItemsPayload(items: EditableItem[]) {
  return items.map((item) => ({ short_link_id: item.short_link_id, label: item.label.trim() }));
}

export function previewPage(
  content: { title: string; description: string | null; display: LinkPageDisplay },
  logo: string | null,
  items: EditableItem[],
): PublicLinkPage {
  return {
    title: content.title,
    description: content.description?.trim() ? content.description : null,
    logo,
    display: { ...content.display },
    links: items
      .filter((item) => item.availability === 'active')
      .map((item) => ({ label: itemLabel(item), url: item.short_url, icon: item.icon })),
  };
}

export function normalizePath(value: string): string {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9-]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-+/, '')
    .slice(0, 64);
}
