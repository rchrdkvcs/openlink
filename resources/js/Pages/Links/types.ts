import type { DestinationHealth } from '@/lib/destinationHealth';
import type { UtmTemplate } from '@/lib/utm';
import type { Domain, Folder, Pagination, Tag } from '@/types/payloads';
import type { RoutingSchema, ShortLink } from '@/types/shortLinks';

export type LinkFilters = {
  search: string;
  status: string;
  tag: string;
  folder: string;
};

export type LinksPageProps = {
  domains: Domain[];
  folders: Folder[];
  tags: Tag[];
  utmTemplates: UtmTemplate[];
  links: ShortLink[];
  inspectedLink: ShortLink | null;
  linksPagination: Pagination;
  filters: LinkFilters;
  routingSchema: RoutingSchema;
  destinationHealth: DestinationHealth[];
};
