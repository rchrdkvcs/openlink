export type LinkPageDisplay = { show_icons: boolean; show_labels: boolean; show_urls: boolean };

type PublicLinkPageEntry = { label: string; url: string; icon: string | null };

export type PublicLinkPage = {
  title: string;
  description: string | null;
  logo: string | null;
  display: LinkPageDisplay;
  links: PublicLinkPageEntry[];
};
