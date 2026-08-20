export type BioPageStatus = 'draft' | 'published' | 'unavailable';

export type BioPageSummary = {
  id: number;
  displayName: string;
  bioUrl: string;
  status: BioPageStatus;
  hasUnpublishedChanges: boolean;
  updatedAt: string;
};

export type BioDomain = {
  id: number;
  hostname: string;
  status: string;
  isDefault?: boolean;
};

export type BioShortLink = {
  id: number;
  slug: string;
  shortUrl: string;
  destinationUrl?: string;
  status: string;
};

export type BioQrCode = {
  id: number;
  name: string;
  token: string;
  public_url: string;
  scans: number;
};

export type BioElementType = 'destination' | 'social' | 'heading' | 'text';
export type BioDestinationType = 'external' | 'email' | 'telephone' | 'short_link';
export type BioSocialPresentation = 'icon' | 'button';

export type BioElement = {
  id?: number;
  clientId: string;
  type: BioElementType;
  label: string;
  text: string;
  sourceType: BioDestinationType;
  url: string;
  shortLinkId: number | null;
  socialService: string;
  presentation: BioSocialPresentation;
  visible: boolean;
  openInNewTab: boolean;
};

export type BioTheme = {
  appearance: 'light' | 'dark' | 'auto';
  backgroundType: 'color' | 'gradient' | 'image';
  backgroundColor: string;
  gradientColor: string;
  textColor: string;
  destinationColor: string;
  destinationTextColor: string;
  destinationStyle: 'solid' | 'outline' | 'soft' | 'transparent';
  destinationRadius: 'square' | 'rounded' | 'large' | 'pill';
  destinationShadow: boolean;
  profileShape: 'circle' | 'rounded' | 'square';
  fontFamily: 'sans' | 'serif' | 'rounded' | 'mono';
  imageFit: 'cover' | 'contain';
  overlayOpacity: number;
};

export type BioDraft = {
  domainId: number | null;
  slug: string;
  displayName: string;
  publicHandle: string;
  biography: string;
  profileImageUrl: string | null;
  backgroundImageUrl: string | null;
  elements: BioElement[];
  theme: BioTheme;
  shareTitle: string;
  shareDescription: string;
  isIndexable: boolean;
  showBranding: boolean;
};

export type BioPageRecord = {
  id: number;
  draft: BioDraft;
  published?: BioDraft | null;
  publishedAt: string | null;
  bioUrl: string;
  hasUnpublishedChanges?: boolean;
  canPublish?: boolean;
  canDelete?: boolean;
};

export type BioPagesIndexProps = {
  bioPages: BioPageSummary[];
  canCreate?: boolean;
};

export type BioPageEditorProps = {
  bioPage: BioPageRecord;
  domains: BioDomain[];
  shortLinks: BioShortLink[];
  qrCodes: BioQrCode[];
  canEdit?: boolean;
  canPublish?: boolean;
  canDelete?: boolean;
  activeEditors?: string[];
};

export type SaveState = 'idle' | 'saving' | 'saved' | 'failed';

export const defaultTheme = (): BioTheme => ({
  appearance: 'dark',
  backgroundType: 'color',
  backgroundColor: '#17171c',
  gradientColor: '#4f46e5',
  textColor: '#f7f7f8',
  destinationColor: '#ffffff',
  destinationTextColor: '#17171c',
  destinationStyle: 'solid',
  destinationRadius: 'large',
  destinationShadow: true,
  profileShape: 'circle',
  fontFamily: 'sans',
  imageFit: 'cover',
  overlayOpacity: 30,
});

export function newBioElement(type: BioElementType): BioElement {
  return {
    clientId: crypto.randomUUID(),
    type,
    label: type === 'social' ? 'Social profile' : type === 'destination' ? 'New link' : '',
    text: type === 'heading' ? 'Section title' : type === 'text' ? 'Add a short introduction.' : '',
    sourceType: 'external',
    url: '',
    shortLinkId: null,
    socialService: type === 'social' ? 'website' : '',
    presentation: type === 'social' ? 'icon' : 'button',
    visible: true,
    openInNewTab: false,
  };
}
