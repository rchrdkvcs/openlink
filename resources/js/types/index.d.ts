export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
  is_instance_admin?: boolean;
  profile_avatar_url?: string | null;
}

export type ShellWorkspace = {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  color?: string | null;
  preferred_domain_id?: number | null;
  pivot?: { role?: string };
};

export type NavigationFolder = { id: number; name: string; links_count: number };

export type Navigation = {
  folders: NavigationFolder[];
  links_count: number;
  unfiled_count: number;
  archived_count: number;
};

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User;
  };
  currentWorkspace?: ShellWorkspace;
  workspaces?: ShellWorkspace[];
  role?: string | null;
  canManageWorkspace?: boolean;
  canEditWorkspace?: boolean;
  navigation?: Navigation;
};
