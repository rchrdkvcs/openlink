export type TargetType = 'short_link' | 'direct';

export type PayloadValue = string | number | boolean | null;

export type PayloadValues = Record<string, PayloadValue>;

export type QrPayloadByType = {
  url: { url: string };
  text: { text: string };
  email: { email: string; subject?: string | null; body?: string | null };
  phone: { phone: string };
  sms: { phone: string; message?: string | null };
  wifi: { ssid: string; encryption: 'WPA' | 'WEP' | 'nopass'; password?: string | null; hidden?: boolean | null };
  vcard: {
    full_name: string;
    organization?: string | null;
    title?: string | null;
    phone?: string | null;
    email?: string | null;
    url?: string | null;
    address?: string | null;
  };
  event: {
    title: string;
    starts_at: string;
    ends_at?: string | null;
    location?: string | null;
    description?: string | null;
  };
  location: { latitude: string | number; longitude: string | number; label?: string | null };
  raw: { content: string };
};

export type PayloadType = keyof QrPayloadByType;

export type QrPayloadTarget =
  | { [K in PayloadType]: { payload_type: K; payload: QrPayloadByType[K] } }[PayloadType]
  | { payload_type: null; payload: null };

export type ShortLinkOption = { id: number; short_url: string; destination_url: string };

export type QrAppearance = {
  foreground_color: string;
  background_color: string;
  margin: number;
  error_correction: string;
  style: string;
  eye_style: string;
  background_transparent: boolean;
};

export type QrCodeRecord = QrPayloadTarget &
  QrAppearance & {
    id: number;
    name: string;
    token: string;
    content: string | null;
    is_direct: boolean;
    short_link_id: number | null;
    short_link: ShortLinkOption | null;
    scans: number;
    size: number;
    has_logo: boolean;
    public_url: string;
    created_at?: string | null;
    updated_at?: string | null;
  };

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

export type PayloadDescriptor = {
  label: string;
  hint: string;
  defaults: PayloadValues;
  fields: PayloadField[];
};

export type PayloadDescriptors = Record<string, PayloadDescriptor>;

export type TargetState = {
  target_type: TargetType;
  short_link_id: string | number;
  payload_type: string;
  payload: PayloadValues;
};

export type ExportFormat = 'png' | 'svg';
