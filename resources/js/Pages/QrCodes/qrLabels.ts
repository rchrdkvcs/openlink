import { displayUrl } from '@/lib/links';

import type { QrCodeRecord, TargetType } from './types';

const compactNumber = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

export function payloadTypeLabel(type: string | null, payloadTypes: Record<string, string>): string {
  return payloadTypes[type ?? ''] ?? type ?? 'Content';
}

export function targetLabel(targetType: TargetType, payloadType: string | null, payloadTypes: Record<string, string>) {
  return targetType === 'short_link' ? 'Short link' : payloadTypeLabel(payloadType, payloadTypes);
}

export function cardSubtitle(
  qr: Pick<QrCodeRecord, 'is_direct' | 'payload_type' | 'short_link' | 'content'>,
  payloadTypes: Record<string, string>,
): string {
  if (qr.is_direct) return payloadTypeLabel(qr.payload_type, payloadTypes);
  return qr.short_link ? displayUrl(qr.short_link.short_url) : (qr.content ?? '');
}

export function pluralizeScans(count: number): string {
  return count === 1 ? 'scan' : 'scans';
}

export function compactCount(count: number): string {
  return compactNumber.format(count);
}

export function studioDescription(label: string, isDirect: boolean, scans: number): string {
  if (isDirect) return `${label} · Scans not tracked`;
  return `${label} · ${scans.toLocaleString()} ${pluralizeScans(scans)}`;
}

export function indexDescription(total: number): string {
  return `${total.toLocaleString()} QR code${total === 1 ? '' : 's'} · Codes for links, Wi-Fi, contact cards and more`;
}
