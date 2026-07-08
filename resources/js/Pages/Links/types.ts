export type Workspace = { id: number; name: string; slug: string; preferred_domain_id?: number | null };

export type Domain = { id: number; hostname: string; status: string; is_default: boolean };

export type Folder = { id: number; name: string };

export type Qr = {
    id: number;
    name: string;
    token: string;
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
    scans: number;
    created_at?: string | null;
};

export type ShortLink = {
    id: number;
    slug: string;
    short_url: string;
    destination_url: string;
    fallback_url?: string | null;
    status: string;
    domain: Domain;
    folder?: Folder | null;
    tags: { id: number; name: string }[];
    qr_codes: Qr[];
    visits: number;
    scans: number;
    is_enabled: boolean;
    activates_at?: string | null;
    expires_at?: string | null;
    visit_limit?: number | null;
    successful_visits: number;
    has_password: boolean;
    routing_rules: RoutingRuleDraft[];
};

export type RoutingCondition = {
    type: string;
    operator: string;
    value?: string | string[] | { from?: string; to?: string } | null;
    timezone?: string;
};

export type RoutingVariantDraft = {
    id?: number;
    client_id?: string;
    name: string;
    is_enabled: boolean;
    destination_url: string;
    weight: number | string;
};

export type RoutingRuleDraft = {
    id?: number;
    client_id?: string;
    name: string;
    type: 'conditional' | 'split_test';
    is_enabled: boolean;
    match_mode: 'all' | 'any';
    conditions: RoutingCondition[];
    destination_url: string;
    variants: RoutingVariantDraft[];
};

export type RoutingOption = { value: string; label: string };

export type RoutingPreset = {
    kind: string;
    label: string;
    description: string;
    conditionType: string;
    ruleType: RoutingRuleDraft['type'];
};

export type RoutingSchema = {
    conditionTypes: RoutingOption[];
    operators: {
        scalar: RoutingOption[];
        time: RoutingOption[];
    };
    valueOptions: Record<string, RoutingOption[]>;
    defaults: Record<string, string>;
    presets: RoutingPreset[];
};

export type CreateLinkFormData = {
    domain_id: number | string;
    folder_id: string;
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

export type EditLinkFormData = {
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
    routing_rules: RoutingRuleDraft[];
};

export type LinkFilters = { search: string; status: string; tag: string };

export type LinkGroup = { key: string; folder: Folder | null; links: ShortLink[] };

export type LinksPageProps = {
    currentWorkspace: Workspace;
    canManageWorkspace: boolean;
    canEditWorkspace: boolean;
    domains: Domain[];
    folders: Folder[];
    tags: { id: number; name: string }[];
    links: ShortLink[];
    routingSchema: RoutingSchema;
};
