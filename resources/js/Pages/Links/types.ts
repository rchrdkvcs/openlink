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
  links: ShortLink[];
  linksPagination: Pagination;
  filters: LinkFilters;
  routingSchema: RoutingSchema;
};
