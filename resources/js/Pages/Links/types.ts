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
};
