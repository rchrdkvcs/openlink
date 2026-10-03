export type Panel = 'content' | 'style' | 'advanced';

export const PANELS: { value: Panel; label: string }[] = [
  { value: 'content', label: 'Content' },
  { value: 'style', label: 'Style' },
  { value: 'advanced', label: 'Advanced' },
];

const PANEL_FIELDS: Record<Panel, string[]> = {
  content: ['name', 'short_link_id', 'payload_type', 'payload'],
  style: ['style', 'eye_style', 'foreground_color', 'background_color', 'background_transparent', 'logo'],
  advanced: ['margin', 'error_correction', 'size'],
};

export function panelForErrors(errorKeys: string[]): Panel | null {
  const target = PANELS.find(({ value }) =>
    errorKeys.some((key) => PANEL_FIELDS[value].some((field) => key === field || key.startsWith(`${field}.`))),
  );

  return target?.value ?? null;
}
