import { CalendarDays, Code2, Contact, Link2, Mail, MapPin, MessageSquare, Phone, Type, Wifi } from '@lucide/vue';

export type QrCodeRecord = {
  id: number;
  name: string;
  token: string;
  payload_type: string | null;
  payload: Record<string, any> | null;
  content: string | null;
  is_direct: boolean;
  short_link_id: number | null;
  short_link: ShortLinkOption | null;
  scans: number;
  size: number;
  foreground_color: string;
  background_color: string;
  margin: number;
  error_correction: string;
  style: string;
  eye_style: string;
  background_transparent: boolean;
  has_logo: boolean;
  public_url: string;
  created_at?: string | null;
  updated_at?: string | null;
};

export type ShortLinkOption = { id: number; short_url: string; destination_url: string };

export type PayloadDescriptor = {
  label: string;
  hint: string;
  defaults: Record<string, any>;
  fields: PayloadField[];
};

export type PayloadDescriptors = Record<string, PayloadDescriptor>;

export type PayloadField = {
  key: string;
  label: string;
  control: 'text' | 'url' | 'email' | 'tel' | 'number' | 'datetime-local' | 'textarea' | 'select' | 'checkbox';
  placeholder?: string;
  rows?: number;
  step?: string;
  class?: string;
  options?: { value: string; label: string }[];
  disabledWhen?: { key: string; value: unknown };
};

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

export function payloadDefaults(type: string, descriptors: PayloadDescriptors) {
  return { ...(descriptors[type]?.defaults ?? descriptors.raw?.defaults ?? { content: '' }) };
}

export function payloadHint(type: string, descriptors: PayloadDescriptors): string {
  return descriptors[type]?.hint ?? descriptors.raw?.hint ?? '';
}

export function payloadIcon(type: string) {
  return PAYLOAD_ICONS[type] ?? PAYLOAD_ICONS.raw;
}
