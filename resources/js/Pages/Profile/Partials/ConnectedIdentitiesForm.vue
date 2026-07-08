<script setup lang="ts">
import UserAvatar from '@/Components/UserAvatar.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { router } from '@inertiajs/vue3';
import { Link2, Trash2 } from '@lucide/vue';

type ConnectedIdentity = {
    id: number;
    provider: string;
    email: string;
    email_verified: boolean;
    avatar_url: string | null;
    is_valid: boolean;
    is_avatar_source: boolean;
};

const props = defineProps<{
    identities: ConnectedIdentity[];
    providers: Record<string, boolean>;
}>();

const providerLabels: Record<string, string> = {
    google: 'Google',
    discord: 'Discord',
};

function providerLabel(provider: string) {
    return providerLabels[provider] ?? provider;
}

function connected(provider: string) {
    return props.identities.some((identity) => identity.provider === provider);
}

function unlink(identity: ConnectedIdentity) {
    router.delete(route('profile.connected-identities.destroy', identity.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-semibold text-foreground">Connected Identities</h2>
            <p class="mt-1 text-sm text-muted">Connect OAuth providers that can sign in to this user.</p>
        </header>

        <div v-if="identities.length" class="mt-6 divide-y divide-border/60 rounded-lg border">
            <div v-for="identity in identities" :key="identity.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <UserAvatar :name="providerLabel(identity.provider)" :src="identity.avatar_url" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium capitalize text-foreground">{{ providerLabel(identity.provider) }}</p>
                        <Badge :variant="identity.is_valid ? 'success' : 'warning'" dot>
                            {{ identity.is_valid ? 'Valid' : 'Email mismatch' }}
                        </Badge>
                        <Badge v-if="identity.is_avatar_source" variant="accent">Avatar</Badge>
                    </div>
                    <p class="truncate text-xs text-faint">{{ identity.email }}</p>
                </div>
                <Button type="button" variant="danger" size="sm" @click="unlink(identity)">
                    <Trash2 class="h-3.5 w-3.5" /> Unlink
                </Button>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <a
                v-for="(enabled, provider) in providers"
                :key="provider"
                :href="route('oauth.redirect', { provider, intent: 'link' })"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-surface px-3.5 text-sm font-medium text-foreground transition-colors hover:border-border-strong hover:bg-elevated"
                :class="{ 'pointer-events-none opacity-50': !enabled || connected(provider) }"
            >
                <Link2 class="h-4 w-4" />
                {{ connected(provider) ? `${providerLabel(provider)} connected` : `Connect ${providerLabel(provider)}` }}
            </a>
        </div>
    </section>
</template>
