import type { AppPlatform } from '@/types/payloads';

export type AppAssociationDraft = {
  platform: AppPlatform;
  team_id: string;
  identifier: string;
  paths: string;
  sha256_cert_fingerprints: string;
};

export type AppAssociationInput =
  | { platform: 'ios'; team_id: string; identifier: string; paths: string[] }
  | { platform: 'android'; identifier: string; sha256_cert_fingerprints: string[] };

export const platformOptions: { value: AppPlatform; label: string }[] = [
  { value: 'ios', label: 'iOS' },
  { value: 'android', label: 'Android' },
];

export function platformLabel(platform: AppPlatform): string {
  return platformOptions.find((option) => option.value === platform)?.label ?? platform;
}

export function emptyDraft(platform: AppPlatform = 'ios'): AppAssociationDraft {
  return { platform, team_id: '', identifier: '', paths: '', sha256_cert_fingerprints: '' };
}

export function splitLines(value: string): string[] {
  return [
    ...new Set(
      value
        .split(/[\n,]+/)
        .map((line) => line.trim())
        .filter(Boolean),
    ),
  ];
}

export function associationInput(draft: AppAssociationDraft): AppAssociationInput {
  if (draft.platform === 'ios') {
    return {
      platform: 'ios',
      team_id: draft.team_id.trim().toUpperCase(),
      identifier: draft.identifier.trim(),
      paths: splitLines(draft.paths),
    };
  }

  return {
    platform: 'android',
    identifier: draft.identifier.trim(),
    sha256_cert_fingerprints: splitLines(draft.sha256_cert_fingerprints).map((value) => value.toUpperCase()),
  };
}

export function formatAssociationFile(content: unknown): string {
  return JSON.stringify(content, null, 2);
}

export function firstFieldError(errors: Record<string, string | undefined>, field: string): string | undefined {
  if (errors[field]) return errors[field];
  const key = Object.keys(errors).find((name) => name.startsWith(`${field}.`) && errors[name]);
  return key ? errors[key] : undefined;
}
