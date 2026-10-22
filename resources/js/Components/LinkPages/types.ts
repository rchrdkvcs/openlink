export type PublicLinkPageEntry = { label: string; url: string };

export type PublicLinkPage = {
  title: string;
  description: string | null;
  logo: string | null;
  links: PublicLinkPageEntry[];
};
