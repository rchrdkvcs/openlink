import type { InertiaForm } from '@inertiajs/vue3';
import { Lock, Mail, UserPlus } from '@lucide/vue';

export type RegistrationMode = 'closed' | 'invite_only' | 'open';

export type InstanceSettingsFields = {
  registration_mode: RegistrationMode;
  require_email_verification: boolean;
  default_domain: string;
  dns_target: string;
  slug_length: string;
  analytics_retention_days: string;
  destination_monitoring: boolean;
  reserved_slugs: string;
  reserved_prefixes: string;
  public_error_branding: boolean;
};

export type InstanceSettingsForm = InertiaForm<InstanceSettingsFields>;

export function instanceSettingsFields(settings: Record<string, any>): InstanceSettingsFields {
  return {
    registration_mode: (settings.registration_mode ?? 'invite_only') as RegistrationMode,
    require_email_verification: Boolean(settings.require_email_verification ?? false),
    default_domain: settings.default_domain ?? 'localhost',
    dns_target: settings.dns_target ?? '',
    slug_length: String(settings.slug_length ?? 6),
    analytics_retention_days: String(settings.analytics_retention_days ?? 365),
    destination_monitoring: Boolean(settings.destination_monitoring ?? false),
    reserved_slugs: (settings.reserved_slugs ?? []).join('\n'),
    reserved_prefixes: (settings.reserved_prefixes ?? []).join('\n'),
    public_error_branding: Boolean(settings.public_error_branding ?? true),
  };
}

export const registrationModes: { value: RegistrationMode; label: string; description: string; icon: unknown }[] = [
  {
    value: 'closed',
    label: 'Private',
    description: 'No one can create an account, even with an invite link. Existing users keep access.',
    icon: Lock,
  },
  {
    value: 'invite_only',
    label: 'Invite link only',
    description: 'A valid workspace invite link is required to create an account.',
    icon: Mail,
  },
  {
    value: 'open',
    label: 'Public',
    description: 'Anyone who reaches the sign-up page can create an account.',
    icon: UserPlus,
  },
];

export function registrationDescription(mode: RegistrationMode): string | undefined {
  return registrationModes.find((option) => option.value === mode)?.description;
}

export function retentionHint(value: string): string {
  const days = Number(value);
  if (!Number.isFinite(days) || days < 30) return 'Visit events older than this are pruned. Minimum 30 days.';
  if (days >= 365) {
    const years = days / 365;
    const rounded = Number.isInteger(years) ? years : Math.round(years * 10) / 10;
    return `Visit events are kept for about ${rounded} ${rounded === 1 ? 'year' : 'years'}, then pruned.`;
  }
  return `Visit events are kept for about ${Math.round(days / 30)} months, then pruned.`;
}
