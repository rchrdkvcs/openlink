export type Workspace = { id: number; name: string; slug: string; preferred_domain_id?: number | null };

export type Domain = { id: number; hostname: string; status: string; is_default: boolean };

export type Folder = { id: number; name: string };

export type Qr = { id: number; name: string; token: string };

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
