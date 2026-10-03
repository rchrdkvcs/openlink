import {
  CalendarDays,
  Code2,
  Contact,
  FileText,
  Link2,
  Mail,
  MapPin,
  MessageSquare,
  Phone,
  Type,
  Wifi,
} from '@lucide/vue';

import type { SelectOption } from '@/lib/controls';

import type { TargetType } from './types';

export const TARGET_OPTIONS: { value: TargetType; label: string; icon: unknown }[] = [
  { value: 'short_link', label: 'Short link', icon: Link2 },
  { value: 'direct', label: 'Content', icon: FileText },
];

export const STYLE_OPTIONS: SelectOption[] = [
  { value: 'square', label: 'Squares' },
  { value: 'rounded', label: 'Rounded' },
  { value: 'dot', label: 'Dots' },
];

export const EYE_STYLE_OPTIONS: SelectOption[] = [
  { value: 'square', label: 'Square' },
  { value: 'rounded', label: 'Rounded' },
  { value: 'circle', label: 'Circle' },
];

export const ERROR_CORRECTION_OPTIONS: SelectOption[] = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'quartile', label: 'Quartile' },
  { value: 'high', label: 'High' },
];

export const EXPORT_SIZE_OPTIONS: SelectOption<number>[] = [512, 1024, 2048, 4096].map((size) => ({
  value: size,
  label: `${size} px`,
}));

const PAYLOAD_ICONS: Record<string, unknown> = {
  url: Link2,
  text: Type,
  email: Mail,
  phone: Phone,
  sms: MessageSquare,
  wifi: Wifi,
  vcard: Contact,
  event: CalendarDays,
  location: MapPin,
  raw: Code2,
};

export function payloadIcon(type: string) {
  return PAYLOAD_ICONS[type] ?? PAYLOAD_ICONS.raw;
}
