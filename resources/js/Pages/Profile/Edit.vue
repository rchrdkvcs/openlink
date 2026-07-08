<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ApiTokensForm from './Partials/ApiTokensForm.vue';
import ConnectedIdentitiesForm from './Partials/ConnectedIdentitiesForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import ProfileAvatarForm from './Partials/ProfileAvatarForm.vue';
import TwoFactorForm from './Partials/TwoFactorForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const tabs = [
    { id: 'profile', label: 'Profile' },
    { id: 'connected-identities', label: 'Connected Identities' },
    { id: 'api-tokens', label: 'API Tokens' },
    { id: 'security', label: 'Security' },
    { id: 'danger-zone', label: 'Danger Zone' },
];

const urlTab = new URLSearchParams(window.location.search).get('tab');
const activeTab = ref(tabs.some((tab) => tab.id === urlTab) ? String(urlTab) : 'profile');
const activeLabel = computed(() => tabs.find((tab) => tab.id === activeTab.value)?.label ?? 'Profile');

function selectTab(tab: string) {
    activeTab.value = tab;
    router.get(route('profile.edit'), { tab }, { preserveScroll: true, preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight">Profile</h1>
                <p class="mt-1 text-sm text-muted">Your identity, connected sign-in methods, API tokens, and security settings.</p>
            </div>

            <div v-if="status" class="mb-4 rounded-lg border bg-surface px-4 py-3 text-sm text-muted">
                {{ status }}
            </div>

            <div class="mb-5 overflow-x-auto border-b">
                <div class="flex min-w-max gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="-mb-px h-10 border-b-2 px-3 text-sm font-medium transition-colors"
                        :class="activeTab === tab.id ? 'border-foreground text-foreground' : 'border-transparent text-muted hover:text-foreground'"
                        @click="selectTab(tab.id)"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div class="card-sheen rounded-lg border bg-surface p-5 sm:p-6">
                <div class="mb-5 sm:hidden">
                    <p class="text-sm font-medium text-foreground">{{ activeLabel }}</p>
                </div>

                <div v-show="activeTab === 'profile'" class="max-w-3xl space-y-8">
                    <ProfileAvatarForm :identities="connectedIdentities" :profile-avatar="profileAvatar" />
                    <div class="border-t" />
                    <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
                </div>

                <ConnectedIdentitiesForm
                    v-show="activeTab === 'connected-identities'"
                    class="max-w-3xl"
                    :identities="connectedIdentities"
                    :providers="oauthProviders"
                />

                <ApiTokensForm
                    v-show="activeTab === 'api-tokens'"
                    class="max-w-3xl"
                    :tokens="apiTokens"
                    :new-token="newApiToken"
                    :can-create="canCreateApiTokens"
                />

                <div v-show="activeTab === 'security'" class="max-w-xl space-y-8">
                    <UpdatePasswordForm />
                    <div class="border-t" />
                    <TwoFactorForm :two-factor="twoFactor" />
                </div>

                <div v-show="activeTab === 'danger-zone'" class="max-w-xl">
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
