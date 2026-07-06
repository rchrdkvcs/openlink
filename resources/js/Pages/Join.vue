<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    invite: {
        token: string;
        workspace: string;
        role: string;
        usable: boolean;
    };
    isMember: boolean;
    canRegister: boolean;
}>();

const page = usePage();
const isAuthenticated = computed(() => Boolean((page.props.auth as { user: unknown | null }).user));

function join() {
    router.post(route('join.store', props.invite.token));
}
</script>

<template>
    <GuestLayout>
        <Head :title="`Join ${invite.workspace}`" />

        <template v-if="!invite.usable">
            <div class="text-center">
                <h1 class="text-lg font-semibold text-foreground">This invite link is no longer valid</h1>
                <p class="mt-2 text-sm text-muted">
                    It may have expired, reached its usage limit, or been revoked. Ask a workspace admin for a new link.
                </p>
                <Link :href="route('home')" class="mt-6 inline-block text-sm font-medium text-foreground underline-offset-4 hover:underline">
                    Back to Openlink
                </Link>
            </div>
        </template>

        <template v-else-if="isMember">
            <div class="text-center">
                <h1 class="text-lg font-semibold text-foreground">You're already a member</h1>
                <p class="mt-2 text-sm text-muted">
                    You already belong to <strong>{{ invite.workspace }}</strong
                    >.
                </p>
                <Button class="mt-6 w-full" type="button" @click="join">Open workspace</Button>
            </div>
        </template>

        <template v-else-if="isAuthenticated">
            <div class="text-center">
                <h1 class="text-lg font-semibold text-foreground">Join {{ invite.workspace }}</h1>
                <p class="mt-2 text-sm text-muted">
                    You've been invited to join as <strong class="capitalize">{{ invite.role }}</strong
                    >.
                </p>
                <Button class="mt-6 w-full" type="button" @click="join">Join workspace</Button>
            </div>
        </template>

        <template v-else>
            <div class="text-center">
                <h1 class="text-lg font-semibold text-foreground">Join {{ invite.workspace }}</h1>
                <p class="mt-2 text-sm text-muted">
                    You've been invited to join as <strong class="capitalize">{{ invite.role }}</strong
                    >. Sign in or create an account to continue.
                </p>
                <div class="mt-6 grid gap-3">
                    <Link v-if="canRegister" :href="route('register', { invite: invite.token })">
                        <Button class="w-full" type="button">Create an account</Button>
                    </Link>
                    <Link :href="route('login')">
                        <Button class="w-full" variant="secondary" type="button">Log in</Button>
                    </Link>
                </div>
                <p v-if="!canRegister" class="mt-3 text-xs text-faint">
                    Registration is closed on this instance — sign in with an existing account, then open this link again.
                </p>
                <p v-else class="mt-3 text-xs text-faint">
                    Already have an account? Log in, then open this invite link again.
                </p>
            </div>
        </template>
    </GuestLayout>
</template>
