import type { ShortLinkOption } from '../QrCodes/types';

export type LinkPageAvailability = 'active' | 'scheduled' | 'expired' | 'disabled' | 'archived' | 'domain_unavailable';

export type LinkPageItem = {
  short_link_id: number;
  label: string | null;
  short_url: string;
  destination_url: string;
  display_label: string;
  availability: LinkPageAvailability;
};

export type LinkPageShortLinkOption = ShortLinkOption & { availability: LinkPageAvailability };

export type LinkPageSummary = {
  id: number;
  name: string;
  path: string;
  title: string;
  is_published: boolean;
  public_url: string;
  items_count: number;
  updated_at?: string | null;
};

export type LinkPageRecord = LinkPageSummary & {
  description: string | null;
  has_logo: boolean;
  logo: string | null;
  published_at: string | null;
  items: LinkPageItem[];
};
