<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { BarChart3, CheckCircle2, CircleAlert, Globe2, Link2, Lock, Mail, UserPlus } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    settings: Record<string, any>;
}>();

const isInstanceAdmin = computed(() => Object.keys(props.settings).length > 0);

const settingsForm = useForm({
    registration_mode: props.settings.registration_mode ?? 'invite_only',
    default_domain: props.settings.default_domain ?? 'localhost',
    dns_target: props.settings.dns_target ?? '',
    slug_length: String(props.settings.slug_length ?? 6),
    analytics_retention_days: String(props.settings.analytics_retention_days ?? 365),
    reserved_slugs: (props.settings.reserved_slugs ?? []).join('\n'),
    reserved_prefixes: (props.settings.reserved_prefixes ?? []).join('\n'),
    public_unavailable_title: props.settings.public_unavailable_title ?? 'This link is unavailable',
    public_unavailable_message: props.settings.public_unavailable_message ?? 'The link cannot be opened right now.',
});

const registrationModes = [
    {
        value: 'closed',
        label: 'Closed',
        description: 'No one can create an account. Existing users keep access.',
        icon: Lock,
    },
    {
        value: 'invite_only',
        label: 'Invite-only',
        description: 'People join only through invite links shared by members.',
        icon: Mail,
    },
    {
        value: 'open',
        label: 'Open',
        description: 'Anyone who reaches the sign-up page can create an account.',
        icon: UserPlus,
    },
];

const retentionHint = computed(() => {
    const days = Number(settingsForm.analytics_retention_days);
    if (!Number.isFinite(days) || days < 30) return 'Visit events older than this are pruned. Minimum 30 days.';
    if (days >= 365) {
        const years = days / 365;
        const rounded = Number.isInteger(years) ? years : Math.round(years * 10) / 10;
        return `Visit events are kept for about ${rounded} ${rounded === 1 ? 'year' : 'years'}, then pruned.`;
    }
    return `Visit events are kept for about ${Math.round(days / 30)} months, then pruned.`;
});

const hasErrors = computed(() => Object.keys(settingsForm.errors).length > 0);
const showSaveBar = computed(() => settingsForm.isDirty || settingsForm.processing || settingsForm.recentlySuccessful);

function updateSettings() {
    settingsForm.patch(route('instance-settings.update'), {
        preserveScroll: true,
        onSuccess: () => settingsForm.defaults(),
    });
}

function discardChanges() {
    settingsForm.reset();
    settingsForm.clearErrors();
}
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-4xl">
                <div class="mb-6">
                    <h1 class="text-xl font-semibold tracking-tight">Settings</h1>
                    <p class="mt-1 text-sm text-muted">Instance-level behaviour for this Openlink installation.</p>
                </div>

                <SectionCard v-if="!isInstanceAdmin">
                    <EmptyState
                        title="Reserved for instance administrators"
                        description="Only an instance administrator can view and change these settings. Workspace options — name, appearance, and preferred domain — live in the workspace switcher."
                    >
                        <template #icon><Lock class="h-5 w-5" /></template>
                    </EmptyState>
                </SectionCard>

                <form v-else class="space-y-4" @submit.prevent="updateSettings">
                    <SectionCard title="Access" description="Who can create an account on this instance.">
                        <template #icon><UserPlus class="h-4 w-4 text-faint" /></template>

                        <div class="p-5">
                            <fieldset class="grid gap-2 sm:grid-cols-3">
                                <label
                                    v-for="mode in registrationModes"
                                    :key="mode.value"
                                    class="flex cursor-pointer flex-col gap-1.5 rounded-md border p-3 transition-colors duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent/40"
                                    :class="
                                        settingsForm.registration_mode === mode.value
                                            ? 'border-accent/60 bg-accent/5'
                                            : 'border-border hover:border-border-strong hover:bg-elevated/40'
                                    "
                                >
                                    <input v-model="settingsForm.registration_mode" type="radio" name="registration_mode" :value="mode.value" class="sr-only" />
                                    <span class="flex items-center gap-2">
                                        <component
                                            :is="mode.icon"
                                            class="h-4 w-4 shrink-0"
                                            :class="settingsForm.registration_mode === mode.value ? 'text-accent' : 'text-faint'"
                                        />
                                        <span class="text-[13px] font-medium text-foreground">{{ mode.label }}</span>
                                    </span>
                                    <span class="text-xs leading-relaxed text-muted">{{ mode.description }}</span>
                                </label>
                            </fieldset>
                            <p v-if="settingsForm.errors.registration_mode" class="mt-2 text-xs text-danger">{{ settingsForm.errors.registration_mode }}</p>
                        </div>
                    </SectionCard>

                    <SectionCard title="Domains &amp; DNS" description="Hostnames used to publish and serve short URLs.">
                        <template #icon><Globe2 class="h-4 w-4 text-faint" /></template>

                        <div class="grid gap-5 p-5 sm:grid-cols-2">
                            <Field
                                label="Default domain"
                                hint="Available to every workspace for short URLs, without DNS setup."
                                :error="settingsForm.errors.default_domain"
                            >
                                <input v-model="settingsForm.default_domain" class="h-9" placeholder="localhost" />
                            </Field>
                            <Field
                                label="DNS target"
                                hint="Where workspace domains should point. Leave empty to use the default domain."
                                :error="settingsForm.errors.dns_target"
                            >
                                <input v-model="settingsForm.dns_target" class="h-9" placeholder="203.0.113.10 or app.example.com" />
                            </Field>
                        </div>
                    </SectionCard>

                    <SectionCard title="Short links" description="Slug generation and the reserved namespace.">
                        <template #icon><Link2 class="h-4 w-4 text-faint" /></template>

                        <div class="grid gap-5 p-5 sm:grid-cols-2">
                            <Field
                                label="Generated slug length"
                                hint="Between 4 and 32 characters."
                                class="sm:max-w-52"
                                :error="settingsForm.errors.slug_length"
                            >
                                <StepperInput v-model="settingsForm.slug_length" :min="4" />
                            </Field>
                            <div class="hidden sm:block" />
                            <Field
                                label="Reserved slugs"
                                hint="One per line. These can never be claimed by a short link."
                                :error="settingsForm.errors.reserved_slugs"
                            >
                                <textarea v-model="settingsForm.reserved_slugs" class="font-mono text-[13px]" rows="6" placeholder="admin&#10;login&#10;settings" />
                            </Field>
                            <Field
                                label="Reserved prefixes"
                                hint="One per line. Slugs starting with these are rejected."
                                :error="settingsForm.errors.reserved_prefixes"
                            >
                                <textarea v-model="settingsForm.reserved_prefixes" class="font-mono text-[13px]" rows="6" placeholder="api/&#10;qr/" />
                            </Field>
                        </div>
                    </SectionCard>

                    <SectionCard title="Analytics" description="How long visit data is kept.">
                        <template #icon><BarChart3 class="h-4 w-4 text-faint" /></template>

                        <div class="p-5">
                            <Field label="Retention (days)" :hint="retentionHint" class="sm:max-w-52" :error="settingsForm.errors.analytics_retention_days">
                                <StepperInput v-model="settingsForm.analytics_retention_days" :step="30" :min="30" />
                            </Field>
                        </div>
                    </SectionCard>

                    <SectionCard title="Unavailable page" description="Shown when a short link is expired, disabled, or scheduled.">
                        <template #icon><CircleAlert class="h-4 w-4 text-faint" /></template>

                        <div class="grid gap-5 p-5 lg:grid-cols-2">
                            <div class="grid content-start gap-5">
                                <Field label="Title" :error="settingsForm.errors.public_unavailable_title">
                                    <input v-model="settingsForm.public_unavailable_title" class="h-9" placeholder="This link is unavailable" />
                                </Field>
                                <Field label="Message" :error="settingsForm.errors.public_unavailable_message">
                                    <textarea v-model="settingsForm.public_unavailable_message" rows="3" placeholder="The link cannot be opened right now." />
                                </Field>
                            </div>

                            <div class="relative overflow-hidden rounded-lg border bg-background">
                                <div class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-[radial-gradient(ellipse_at_top,hsl(var(--warning)/0.08),transparent_65%)]" />
                                <p class="absolute left-3 top-2.5 text-[11px] font-medium uppercase tracking-wide text-faint">Preview</p>
                                <div class="grid min-h-full place-items-center px-6 py-10">
                                    <div class="card-sheen relative w-full max-w-xs rounded-xl border bg-surface p-5 text-center shadow-2xl shadow-black/30">
                                        <div class="mx-auto mb-3 grid h-9 w-9 place-items-center rounded-lg border bg-elevated text-warning">
                                            <CircleAlert class="h-4 w-4" />
                                        </div>
                                        <p class="break-words text-sm font-semibold text-foreground">
                                            {{ settingsForm.public_unavailable_title || 'This link is unavailable' }}
                                        </p>
                                        <p class="mt-1.5 break-words text-[13px] text-muted">
                                            {{ settingsForm.public_unavailable_message || 'The link cannot be opened right now.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </SectionCard>

                    <div class="pointer-events-none sticky bottom-4 z-20">
                        <Transition
                            enter-active-class="transition duration-200 ease-emphasized-out"
                            enter-from-class="translate-y-2 opacity-0"
                            enter-to-class="translate-y-0 opacity-100"
                            leave-active-class="transition duration-150 ease-in-out"
                            leave-to-class="translate-y-2 opacity-0"
                        >
                            <div
                                v-if="showSaveBar"
                                class="pointer-events-auto mx-auto flex w-full max-w-xl items-center justify-between gap-3 rounded-lg border bg-overlay/95 py-2 pl-4 pr-2 shadow-2xl shadow-black/40 backdrop-blur-md"
                            >
                                <p v-if="hasErrors" class="truncate text-[13px] text-danger">Some fields need attention.</p>
                                <p v-else-if="settingsForm.isDirty || settingsForm.processing" class="truncate text-[13px] text-muted">
                                    You have unsaved changes.
                                </p>
                                <p v-else class="inline-flex items-center gap-1.5 truncate text-[13px] text-success">
                                    <CheckCircle2 class="h-4 w-4 shrink-0" /> Instance settings saved.
                                </p>

                                <div v-if="settingsForm.isDirty || settingsForm.processing" class="flex shrink-0 items-center gap-2">
                                    <Button variant="ghost" size="sm" type="button" :disabled="settingsForm.processing" @click="discardChanges">
                                        Discard
                                    </Button>
                                    <Button size="sm" :loading="settingsForm.processing">Save changes</Button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
