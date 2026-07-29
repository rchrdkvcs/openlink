<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Button from '@/Components/ui/Button.vue';
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import Field from '@/Components/ui/Field.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Link2, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

type Domain = { id: number; hostname: string; is_default: boolean };
type InviteLink = { id: number; role: string; url: string };

const props = defineProps<{
    workspace: { id: number; name: string; slug: string } | null;
    domains: Domain[];
    inviteLinks: InviteLink[];
    hasLink: boolean;
}>();

// Step 1 happens while the user has no workspace; steps 2 and 3 after.
// Inertia reuses the component instance across the redirect, so advance
// the step when the workspace prop appears instead of only on mount.
const step = ref(props.workspace ? 2 : 1);

watch(
    () => props.workspace,
    (workspace) => {
        if (workspace && step.value === 1) {
            step.value = 2;
        }
    },
);

const workspaceForm = useForm({ name: '' });

function createWorkspace() {
    workspaceForm.post(route('onboarding.workspace'));
}

function firstDomainId() {
    return props.domains[0]?.id ?? null;
}

const linkForm = useForm({
    domain_id: firstDomainId(),
    destination_url: '',
    slug: '',
});

watch(
    () => props.domains,
    () => {
        if (!linkForm.domain_id) {
            linkForm.domain_id = firstDomainId();
        }
    },
    { immediate: true },
);

function createFirstLink() {
    linkForm.post(route('short-links.store'), {
        preserveScroll: true,
        onSuccess: () => step.value = 3,
    });
}

const inviteForm = useForm({ role: 'editor', expires_in_days: null, max_uses: null });

function createInviteLink() {
    inviteForm.post(route('invite-links.store'), { preserveScroll: true });
}

const teamInviteLink = computed(() => props.inviteLinks[0] ?? null);
const copied = ref(false);

async function copyInviteLink() {
    if (!teamInviteLink.value) return;
    await navigator.clipboard.writeText(teamInviteLink.value.url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function finish() {
    router.post(route('onboarding.complete'));
}

const steps = [
    { number: 1, label: 'Workspace' },
    { number: 2, label: 'First link' },
    { number: 3, label: 'Your team' },
];
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-4 py-12">
        <Head title="Welcome" />

        <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.12),transparent_65%)]" />

        <div class="relative w-full max-w-md animate-slide-up">
            <div class="mb-8 flex justify-center">
                <ApplicationLogo class="h-10 w-auto" />
            </div>

            <div class="mb-6 flex items-center justify-center gap-2">
                <template v-for="(item, index) in steps" :key="item.number">
                    <div class="flex items-center gap-2">
                        <span
                            class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold"
                            :class="step >= item.number ? 'bg-foreground text-background' : 'border border-border text-faint'"
                        >
                            <Check v-if="step > item.number" class="h-3.5 w-3.5" />
                            <template v-else>{{ item.number }}</template>
                        </span>
                        <span class="text-xs" :class="step >= item.number ? 'text-foreground' : 'text-faint'">{{ item.label }}</span>
                    </div>
                    <span v-if="index < steps.length - 1" class="h-px w-6 bg-border" />
                </template>
            </div>

            <div class="card-sheen rounded-xl border bg-surface p-6 shadow-2xl shadow-black/30">
                <template v-if="step === 1">
                    <h1 class="text-lg font-semibold text-foreground">Create your workspace</h1>
                    <p class="mt-1 text-sm text-muted">
                        A workspace groups your links, domains, and team. You can create more later.
                    </p>
                    <form class="mt-5 space-y-4" @submit.prevent="createWorkspace">
                        <Field label="Workspace name" :error="workspaceForm.errors.name">
                            <input v-model="workspaceForm.name" class="h-9" placeholder="Acme, Marketing, Personal…"
                                   autofocus required />
                        </Field>
                        <Button class="w-full" :loading="workspaceForm.processing">Create workspace</Button>
                    </form>
                </template>

                <template v-else-if="step === 2">
                    <div class="flex items-center gap-2">
                        <Link2 class="h-4 w-4 text-faint" />
                        <h1 class="text-lg font-semibold text-foreground">Create your first short link</h1>
                    </div>
                    <p class="mt-1 text-sm text-muted">Paste a destination URL — we'll generate the short link for you.</p>

                    <div v-if="hasLink" class="mt-5 rounded-md border border-success/25 bg-success/10 px-3 py-2.5 text-sm text-foreground">
                        Your first link is ready. You can manage it from the dashboard.
                    </div>
                    <form v-else class="mt-5 space-y-4" @submit.prevent="createFirstLink">
                        <Field label="Destination URL" :error="linkForm.errors.destination_url">
                            <input v-model="linkForm.destination_url" type="url" class="h-9" placeholder="https://example.com/some/long/url" required />
                        </Field>
                        <Field v-if="domains.length > 1" label="Domain" :error="linkForm.errors.domain_id">
                            <select v-model="linkForm.domain_id" class="h-9">
                                <option v-for="domain in domains" :key="domain.id" :value="domain.id">{{ domain.hostname }}</option>
                            </select>
                        </Field>

                        <div class="flex flex-col gap-2">
                            <Button class="w-full" :loading="linkForm.processing">Create link</Button>
                            <Button v-if="hasLink" size="sm" type="button" @click="step = 3">Continue</Button>
                            <Button v-else variant="ghost" size="sm" type="button" @click="step = 3">Skip for now</Button>
                        </div>
                    </form>
                </template>

                <template v-else>
                    <div class="flex items-center gap-2">
                        <Users class="h-4 w-4 text-faint" />
                        <h1 class="text-lg font-semibold text-foreground">Invite your team</h1>
                    </div>
                    <p class="mt-1 text-sm text-muted">
                        Share an invite link — anyone who opens it joins <strong>{{ workspace?.name }}</strong> with the role you pick.
                    </p>

                    <div v-if="teamInviteLink" class="mt-5 space-y-3">
                        <div class="flex items-center gap-2 rounded-md border bg-elevated/40 px-3 py-2.5">
                            <code class="min-w-0 flex-1 truncate text-xs text-muted">{{ teamInviteLink.url }}</code>
                            <Button variant="secondary" size="sm" type="button" @click="copyInviteLink">
                                <CopyCheckIcon :copied="copied" />
                                {{ copied ? 'Copied' : 'Copy' }}
                            </Button>
                        </div>
                        <p class="text-xs text-faint">
                            Joins as <span class="capitalize">{{ teamInviteLink.role }}</span> · manage links from the Members page.
                        </p>
                    </div>
                    <form v-else class="mt-5 space-y-4" @submit.prevent="createInviteLink">
                        <Field label="New members join as" :error="inviteForm.errors.role">
                            <select v-model="inviteForm.role" class="h-9">
                                <option value="admin">Admin</option>
                                <option value="editor">Editor</option>
                                <option value="viewer">Viewer</option>
                            </select>
                        </Field>
                        <Button class="w-full" variant="secondary" :loading="inviteForm.processing">
                            <Link2 class="h-4 w-4" /> Generate invite link
                        </Button>
                    </form>

                    <div class="mt-6 flex justify-between">
                        <Button variant="ghost" size="sm" type="button" @click="step = 2">Back</Button>
                        <Button size="sm" type="button" @click="finish">{{ teamInviteLink ? 'Finish' : 'Skip and finish' }}</Button>
                    </div>
                </template>
            </div>

            <p v-if="step === 1" class="mt-6 text-center text-xs text-faint">
                Joining an existing team? Ask a teammate for an invite link, or
                <Link :href="route('dashboard')" class="underline-offset-4 hover:underline">go to your dashboard</Link>.
            </p>
        </div>
    </div>
</template>
