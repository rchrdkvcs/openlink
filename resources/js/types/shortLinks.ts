import type { Domain, Folder, Tag } from '@/types/payloads';

export type LinkStatus = 'active' | 'scheduled' | 'expired' | 'disabled' | 'archived';

export type ShortLink = {
  id: number;
  slug: string;
  short_url: string;
  destination_url: string;
  fallback_url?: string | null;
  status: LinkStatus;
  domain: Domain;
  folder?: Folder | null;
  tags: Tag[];
  qr_code_count: number;
  visits: number;
  scans: number;
  is_enabled: boolean;
  archived_at?: string | null;
  created_at?: string | null;
  activates_at?: string | null;
  expires_at?: string | null;
  visit_limit?: number | null;
  successful_visits: number;
  has_password: boolean;
  revision: number;
  preview?: LinkPreview;
  conversion_goal?: string | null;
  routing_rules: RoutingRuleDraft[];
};

type LinkPreview = {
  title: string | null;
  description: string | null;
  image_url: string | null;
};

export type RoutingRange = { from?: string; to?: string };

export type RoutingCondition = {
  type: string;
  operator: string;
  value?: string | string[] | RoutingRange | null;
  timezone?: string;
};

export type RoutingVariantDraft = {
  id?: number;
  client_id?: string;
  position?: number;
  name: string;
  is_enabled: boolean;
  destination_url: string;
  weight: number | string;
};

export type RoutingRuleDraft = {
  id?: number;
  client_id?: string;
  position?: number;
  name: string;
  type: 'conditional' | 'split_test';
  is_enabled: boolean;
  match_mode: 'all' | 'any';
  conditions: RoutingCondition[];
  destination_url: string;
  variants: RoutingVariantDraft[];
};

export type RoutingOption = { value: string; label: string };

type RoutingPreset = {
  kind: string;
  label: string;
  description: string;
  conditionType: string;
  ruleType: RoutingRuleDraft['type'];
};

export type RoutingOperatorGroup = 'scalar' | 'time';

export type RoutingConditionType = RoutingOption & {
  operatorGroup: RoutingOperatorGroup;
  zoned?: boolean;
  defaultOperator?: string;
  defaultValue?: RoutingCondition['value'];
};

export type RoutingSchema = {
  conditionTypes: RoutingConditionType[];
  operators: Record<RoutingOperatorGroup, RoutingOption[]>;
  valueOptions: Record<string, RoutingOption[]>;
  defaults: Record<string, string>;
  presets: RoutingPreset[];
};

type RevisionAction = 'baseline' | 'created' | 'updated' | 'moved' | 'archived' | 'restored';

export type MutationChannel = 'dashboard' | 'api' | 'system';

export type RevisionChange = { field: string; before: unknown; after: unknown };

export type LinkRevision = {
  number: number;
  action: RevisionAction;
  channel: MutationChannel;
  actor: { id: number; name: string } | null;
  restored_from: number | null;
  changes: RevisionChange[];
  created_at: string;
};

type RestorePreview = {
  restorable: boolean;
  reason: string | null;
  changes: RevisionChange[];
  notes: string[];
};

export type LinkRevisionDetail = LinkRevision & {
  schema_version: number;
  snapshot: Record<string, unknown>;
  restore: RestorePreview;
};

export type LinkRevisionPage = {
  data: LinkRevision[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; current_revision: number };
};
