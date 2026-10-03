<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import SettingsLayout from '@/Layouts/SettingsLayout.vue';

import ApiTokensForm from './Partials/ApiTokensForm.vue';
import ConnectedIdentitiesForm from './Partials/ConnectedIdentitiesForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import ProfileAvatarForm from './Partials/ProfileAvatarForm.vue';
import TwoFactorForm from './Partials/TwoFactorForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

type ConnectedIdentity = {
  id: number;
  provider: string;
  email: string;
  email_verified: boolean;
  avatar_url: string | null;
  is_valid: boolean;
  is_avatar_source: boolean;
  created_at: string;
};

type ApiToken = {
  id: number;
  name: string;
  created_at: string;
  last_used_at: string | null;
};

const props = defineProps<{
  mustVerifyEmail?: boolean;
  status?: string;
  profileAvatar: {
    url: string | null;
    source_id: number | null;
  };
  connectedIdentities: ConnectedIdentity[];
  oauthProviders: Record<string, boolean>;
  apiTokens: ApiToken[];
  newApiToken?: { name: string; token: string } | null;
  canCreateApiTokens: boolean;
  twoFactor: {
    enabled: boolean;
    pendingSecret?: string | null;
    otpauthUrl?: string | null;
  };
}>();

type Section = 'profile' | 'connected-identities' | 'security' | 'api-tokens' | 'danger-zone';

const sections: Record<Section, { title: string; description: string }> = {
  profile: { title: 'Profile', description: 'How you appear to your teammates.' },
  'connected-identities': {
    title: 'Sign-in methods',
    description: 'Accounts you can use to sign in to Openlink.',
  },
  security: { title: 'Security', description: 'Your password and two-factor authentication.' },
  'api-tokens': { title: 'API tokens', description: 'Credentials for scripts, extensions and other clients.' },
  'danger-zone': { title: 'Delete account', description: 'Permanently remove your account and its data.' },
};

const page = usePage();

const lastTab = ref<Section>('profile');

const tab = computed<Section>(() => {
  const value = new URL(page.url, window.location.origin).searchParams.get('tab');
  if (value && value in sections) return value as Section;
  return value === null ? lastTab.value : 'profile';
});

watch(tab, (value) => (lastTab.value = value), { immediate: true });

const section = computed(() => sections[tab.value]);

const banner = computed(() => (props.status && props.status !== 'verification-link-sent' ? props.status : null));
</script>

<template>
  <Head :title="section.title" />

  <SettingsLayout :title="section.title" :description="section.description">
    <div
      v-if="banner"
      role="status"
      class="flex items-start gap-2.5 rounded-xl border bg-surface px-4 py-3 text-[13px] text-muted"
    >
      <Info class="mt-0.5 h-4 w-4 shrink-0 text-faint" />
      <p>{{ banner }}</p>
    </div>

    <template v-if="tab === 'profile'">
      <ProfileAvatarForm :identities="connectedIdentities" :profile-avatar="profileAvatar" />
      <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
    </template>

    <ConnectedIdentitiesForm
      v-else-if="tab === 'connected-identities'"
      :identities="connectedIdentities"
      :providers="oauthProviders"
    />

    <template v-else-if="tab === 'security'">
      <UpdatePasswordForm />
      <TwoFactorForm :two-factor="twoFactor" />
    </template>

    <ApiTokensForm
      v-else-if="tab === 'api-tokens'"
      :tokens="apiTokens"
      :new-token="newApiToken"
      :can-create="canCreateApiTokens"
    />

    <DeleteUserForm v-else />
  </SettingsLayout>
</template>
