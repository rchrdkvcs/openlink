export type RangePreset = '24h' | '7d' | '14d' | '30d' | '90d' | '12m' | 'custom';

export type ReportRange = {
    preset: RangePreset;
    from?: string;
    to?: string;
    bucket: 'hour' | 'day' | 'month';
};

export type Summary = {
    visits: number;
    scans: number;
    visitors: number;
    blocked: number;
    bots: number;
    active_links: number;
    success_rate: number | null;
    visits_change: number | null;
    scans_change: number | null;
    visitors_change: number | null;
    blocked_change: number | null;
};

export type TimePoint = {
    bucket: string;
    visits: number;
    scans: number;
    visitors: number;
    blocked: number;
};

export type BreakdownRow = {
    label: string;
    count: number;
    visitors: number;
    share: number;
};

export type OutcomeRow = {
    outcome: string;
    count: number;
    share: number;
};

export type TopLink = {
    id: number;
    slug: string;
    short_url: string | null;
    destination_url: string | null;
    visits: number;
    scans: number;
    visitors: number;
    total: number;
};

export type TopQrCode = {
    id: number;
    name: string;
    link_slug: string | null;
    scans: number;
    visitors: number;
};

export type BarListRow = {
    label: string;
    /** Optional human-readable replacement for the raw label. */
    display?: string;
    /** Optional prefix rendered before the label (e.g. a flag emoji). */
    prefix?: string;
    count: number;
    share: number;
};

export type BreakdownTab = {
    key: string;
    label: string;
    rows: BarListRow[];
    empty?: string;
};

export type Report = {
    range: ReportRange;
    summary: Summary;
    timeseries: TimePoint[];
    breakdowns: {
        referrers: BreakdownRow[];
        channels: BreakdownRow[];
        countries: BreakdownRow[];
        languages: BreakdownRow[];
        devices: BreakdownRow[];
        browsers: BreakdownRow[];
        os: BreakdownRow[];
        utm_sources: BreakdownRow[];
        utm_mediums: BreakdownRow[];
        utm_campaigns: BreakdownRow[];
    };
    outcomes: OutcomeRow[];
    top_links: TopLink[];
    top_qr_codes: TopQrCode[];
};

// Series colors, validated for CVD separation and contrast against the app's
// dark surface (see docs/adr/0007). Visits wears the product accent.
export const SERIES_COLORS = {
    visits: '#707bdb',
    scans: '#c47d20',
} as const;

const compact = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });
const full = new Intl.NumberFormat('en');
const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });
const languageNames = new Intl.DisplayNames(['en'], { type: 'language' });

export function formatCompact(value: number): string {
    return compact.format(value);
}

export function formatNumber(value: number): string {
    return full.format(value);
}

export function countryName(code: string): string {
    try {
        return regionNames.of(code.toUpperCase()) ?? code;
    } catch {
        return code;
    }
}

export function countryFlag(code: string): string {
    const upper = code.toUpperCase();
    if (!/^[A-Z]{2}$/.test(upper)) return '';
    return String.fromCodePoint(...[...upper].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
}

export function languageName(code: string): string {
    try {
        return languageNames.of(code.toLowerCase()) ?? code;
    } catch {
        return code;
    }
}

export const OUTCOME_LABELS: Record<string, string> = {
    success: 'Successful',
    password_failed: 'Wrong password',
    expired: 'Expired link',
    disabled: 'Disabled link',
    scheduled: 'Not yet active',
    not_found: 'Not found',
    domain_unavailable: 'Domain unavailable',
    visit_limit_reached: 'Visit limit reached',
    archived: 'Archived link',
};

export const CHANNEL_LABELS: Record<string, string> = {
    direct: 'Direct / none',
    search: 'Search engines',
    social: 'Social networks',
    video: 'Video platforms',
    email: 'Email',
    messaging: 'Messaging apps',
    ai: 'AI assistants',
    referral: 'Other websites',
};

export const DEVICE_LABELS: Record<string, string> = {
    desktop: 'Desktop',
    mobile: 'Mobile',
    tablet: 'Tablet',
    bot: 'Bot',
    unknown: 'Unknown',
};

/** Format a time-series bucket key ("2026-07-06", "2026-07-06 14:00", "2026-07") for axis and tooltip use. */
export function formatBucket(bucket: string, unit: ReportRange['bucket'], style: 'short' | 'long' = 'short'): string {
    if (unit === 'hour') {
        const [date, time] = bucket.split(' ');
        const day = new Date(`${date}T00:00:00`);
        const dayLabel = day.toLocaleDateString('en', { month: 'short', day: 'numeric' });
        return style === 'short' ? time : `${dayLabel}, ${time}`;
    }

    if (unit === 'month') {
        const day = new Date(`${bucket}-01T00:00:00`);
        return day.toLocaleDateString('en', { month: 'short', year: style === 'short' ? '2-digit' : 'numeric' });
    }

    const day = new Date(`${bucket}T00:00:00`);
    return day.toLocaleDateString('en', {
        month: 'short',
        day: 'numeric',
        ...(style === 'long' ? { year: 'numeric', weekday: 'short' } : {}),
    });
}
