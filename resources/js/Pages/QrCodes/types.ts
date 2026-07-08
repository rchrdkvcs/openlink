import { CalendarDays, Code2, Contact, Link2, Mail, MapPin, MessageSquare, Phone, Type, Wifi } from '@lucide/vue';

export type QrCodeRecord = {
    id: number;
    name: string;
    token: string;
    payload_type: string;
    payload: Record<string, any>;
    content: string;
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

export const PAYLOAD_DEFAULTS: Record<string, Record<string, any>> = {
    url: { url: '' },
    text: { text: '' },
    email: { email: '', subject: '', body: '' },
    phone: { phone: '' },
    sms: { phone: '', message: '' },
    wifi: { ssid: '', encryption: 'WPA', password: '', hidden: false },
    vcard: { full_name: '', organization: '', title: '', phone: '', email: '', url: '', address: '' },
    event: { title: '', starts_at: '', ends_at: '', location: '', description: '' },
    location: { latitude: '', longitude: '', label: '' },
    raw: { content: '' },
};

export function payloadDefaults(type: string) {
    return { ...(PAYLOAD_DEFAULTS[type] ?? PAYLOAD_DEFAULTS.raw) };
}

export type PayloadTypeMeta = { icon: unknown; hint: string };

export const PAYLOAD_TYPE_META: Record<string, PayloadTypeMeta> = {
    url: { icon: Link2, hint: 'Open a web page' },
    text: { icon: Type, hint: 'Show plain text' },
    email: { icon: Mail, hint: 'Compose an email' },
    phone: { icon: Phone, hint: 'Start a phone call' },
    sms: { icon: MessageSquare, hint: 'Prefill a text message' },
    wifi: { icon: Wifi, hint: 'Join a Wi-Fi network' },
    vcard: { icon: Contact, hint: 'Share a contact card' },
    event: { icon: CalendarDays, hint: 'Add a calendar event' },
    location: { icon: MapPin, hint: 'Open a map location' },
    raw: { icon: Code2, hint: 'Any custom QR payload' },
};

export function payloadTypeMeta(type: string): PayloadTypeMeta {
    return PAYLOAD_TYPE_META[type] ?? PAYLOAD_TYPE_META.raw;
}
