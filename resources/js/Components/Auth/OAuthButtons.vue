<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    providers: Record<string, boolean>;
    intent: 'login' | 'register';
    invite?: string | null;
}>();

const providerEntries = (providers: Record<string, boolean>) => Object.keys(providers);

const providerLabel = (provider: string) =>
    ({
        google: 'Google',
        apple: 'Apple',
        discord: 'Discord',
    })[provider] ?? provider;
</script>

<template>
    <div v-if="providerEntries(providers).length" class="space-y-4">
        <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-border" />
            <span class="text-xs font-medium uppercase text-faint">or</span>
            <div class="h-px flex-1 bg-border" />
        </div>

        <div class="grid gap-2">
            <Link
                v-for="provider in providerEntries(providers)"
                :key="provider"
                :href="route('oauth.redirect', { provider, intent, invite })"
                class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-surface px-3.5 text-sm font-medium text-foreground transition-colors hover:border-border-strong hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
            >
                Continue with {{ providerLabel(provider) }}
            </Link>
        </div>
    </div>
</template>
