<script setup lang="ts">
defineProps<{
    providers: Record<string, boolean>;
    intent: 'login' | 'register';
    invite?: string | null;
}>();

const providerEntries = (providers: Record<string, boolean>) => Object.keys(providers);

const providerLabels: Record<string, string> = {
    google: 'Google',
    discord: 'Discord',
};

const providerLabel = (provider: string) => providerLabels[provider] ?? provider;

const providerButtonClass = (provider: string) =>
    [
        'inline-flex h-10 items-center justify-center gap-2 rounded-md px-3.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40',
        provider === 'google'
            ? 'border border-border bg-white text-[#1f1f1f] hover:bg-[#f8fafd]'
            : 'border border-[#5865f2] bg-[#5865f2] text-white hover:bg-[#4752c4]',
    ].join(' ');
</script>

<template>
    <div v-if="providerEntries(providers).length" class="space-y-4">
        <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-border" />
            <span class="text-xs font-medium uppercase text-faint">or</span>
            <div class="h-px flex-1 bg-border" />
        </div>

        <div class="grid gap-2">
            <a
                v-for="provider in providerEntries(providers)"
                :key="provider"
                :href="route('oauth.redirect', { provider, intent, invite })"
                :class="providerButtonClass(provider)"
            >
                <svg v-if="provider === 'google'" class="h-4 w-4" viewBox="0 0 18 18" aria-hidden="true">
                    <path
                        fill="#4285f4"
                        d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62Z"
                    />
                    <path
                        fill="#34a853"
                        d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.72H.96v2.33A9 9 0 0 0 9 18Z"
                    />
                    <path
                        fill="#fbbc05"
                        d="M3.97 10.7A5.41 5.41 0 0 1 3.68 9c0-.59.1-1.16.29-1.7V4.97H.96A9 9 0 0 0 0 9c0 1.45.35 2.82.96 4.03l3.01-2.33Z"
                    />
                    <path
                        fill="#ea4335"
                        d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.97L3.97 7.3C4.68 5.16 6.66 3.58 9 3.58Z"
                    />
                </svg>

                <svg v-else-if="provider === 'discord'" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path
                        d="M20.32 4.37A19.8 19.8 0 0 0 15.36 2.8a13.78 13.78 0 0 0-.64 1.32 18.42 18.42 0 0 0-5.44 0 13.78 13.78 0 0 0-.64-1.32 19.74 19.74 0 0 0-4.97 1.57C.53 9.1-.32 13.73.1 18.3a19.9 19.9 0 0 0 6.1 3.08c.49-.66.93-1.37 1.3-2.1-.72-.27-1.4-.6-2.05-.98.17-.12.34-.25.5-.38a14.15 14.15 0 0 0 12.1 0c.16.13.33.26.5.38-.65.39-1.33.71-2.05.98.37.73.81 1.44 1.3 2.1a19.86 19.86 0 0 0 6.1-3.08c.5-5.3-.84-9.89-3.58-13.93ZM8.02 15.49c-1.18 0-2.14-1.08-2.14-2.42 0-1.33.94-2.42 2.14-2.42 1.2 0 2.16 1.1 2.14 2.42 0 1.34-.95 2.42-2.14 2.42Zm7.96 0c-1.18 0-2.14-1.08-2.14-2.42 0-1.33.94-2.42 2.14-2.42 1.2 0 2.16 1.1 2.14 2.42 0 1.34-.94 2.42-2.14 2.42Z"
                    />
                </svg>

                Continue with {{ providerLabel(provider) }}
            </a>
        </div>
    </div>
</template>
