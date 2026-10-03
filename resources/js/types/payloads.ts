export type WorkspaceRole = 'owner' | 'admin' | 'editor' | 'viewer';

export type WorkspaceSummary = {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  color?: string | null;
  preferred_domain_id?: number | null;
};

export type DomainDnsRecord = { type: string; value: string };

export type Domain = {
  id: number;
  hostname: string;
  status: string;
  is_default: boolean;
  workspace_id?: number | null;
  expected_txt_name?: string;
  expected_txt?: string;
  failure_reason?: string | null;
  ownership_verified?: boolean;
  dns_pointed?: boolean;
  dns_check_error?: string | null;
  dns_record?: DomainDnsRecord;
};

export type DomainSetup = Domain &
  Required<Pick<Domain, 'expected_txt_name' | 'expected_txt' | 'ownership_verified' | 'dns_pointed' | 'dns_record'>> & {
    failure_reason: string | null;
    dns_check_error: string | null;
  };

export type Folder = { id: number; name: string };

export type Tag = { id: number; name: string };

export type Pagination = { currentPage: number; lastPage: number; total: number; perPage?: number };

export type InviteLink = {
  id: number;
  role: string;
  token: string;
  url: string;
  expires_at: string | null;
  max_uses: number | null;
  uses: number;
  is_usable: boolean;
  created_at: string | null;
};

export type MemberUser = { id: number; name: string; email: string; profile_avatar_url?: string | null };

export type Member = { id: number; role: string; created_at: string; user: MemberUser };
