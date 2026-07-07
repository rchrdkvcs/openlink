<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';

const props = defineProps<{
    settings: Record<string, any>;
}>();

const settingsForm = useForm({
    registration_mode: props.settings.registration_mode ?? 'invite_only',
    default_domain: props.settings.default_domain ?? 'localhost',
    slug_length: props.settings.slug_length ?? 6,
    analytics_retention_days: props.settings.analytics_retention_days ?? 365,
    reserved_slugs: (props.settings.reserved_slugs ?? []).join('\n'),
    reserved_prefixes: (props.settings.reserved_prefixes ?? []).join('\n'),
    public_unavailable_title: props.settings.public_unavailable_title ?? 'This link is unavailable',
    public_unavailable_message: props.settings.public_unavailable_message ?? 'The link cannot be opened right now.',
});

function updateSettings() {
    settingsForm.patch(route('instance-settings.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight">Settings</h1>
                <p class="mt-1 text-sm text-muted">Instance-level behaviour for this Openlink installation.</p>
            </div>

            <div class="space-y-6">
                <SectionCard v-if="Object.keys(settings).length" title="Instance" description="Applies to the whole Openlink installation.">
                    <template #icon><Settings class="h-4 w-4 text-faint" /></template>

                    <form class="grid gap-5 p-5 sm:grid-cols-2" @submit.prevent="updateSettings">
                        <Field label="Registration mode" :error="settingsForm.errors.registration_mode">
                            <select v-model="settingsForm.registration_mode" class="h-9">
                                <option value="closed">Closed</option>
                                <option value="invite_only">Invite-only</option>
                                <option value="open">Open</option>
                            </select>
                        </Field>
                        <Field label="Default domain" :error="settingsForm.errors.default_domain">
                            <input v-model="settingsForm.default_domain" class="h-9" placeholder="localhost" />
                        </Field>
                        <Field label="Generated slug length" :error="settingsForm.errors.slug_length">
                            <input v-model="settingsForm.slug_length" type="number" class="h-9" min="4" max="32" />
                        </Field>
                        <Field label="Analytics retention (days)" :error="settingsForm.errors.analytics_retention_days">
                            <input v-model="settingsForm.analytics_retention_days" type="number" class="h-9" min="30" />
                        </Field>
                        <Field label="Unavailable page title" class="sm:col-span-2" :error="settingsForm.errors.public_unavailable_title">
                            <input v-model="settingsForm.public_unavailable_title" class="h-9" placeholder="This link is unavailable" />
                        </Field>
                        <Field label="Unavailable page message" class="sm:col-span-2" :error="settingsForm.errors.public_unavailable_message">
                            <input v-model="settingsForm.public_unavailable_message" class="h-9" placeholder="The link cannot be opened right now." />
                        </Field>
                        <Field label="Reserved slugs" hint="One per line." :error="settingsForm.errors.reserved_slugs">
                            <textarea v-model="settingsForm.reserved_slugs" class="font-mono text-[13px]" rows="4" placeholder="admin&#10;login&#10;settings" />
                        </Field>
                        <Field label="Reserved prefixes" hint="One per line." :error="settingsForm.errors.reserved_prefixes">
                            <textarea v-model="settingsForm.reserved_prefixes" class="font-mono text-[13px]" rows="4" placeholder="api/&#10;qr/" />
                        </Field>
                        <div class="flex items-center gap-3 sm:col-span-2">
                            <Button :loading="settingsForm.processing">Save instance settings</Button>
                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p v-if="settingsForm.recentlySuccessful" class="text-[13px] text-success">Saved.</p>
                            </Transition>
                        </div>
                    </form>
                </SectionCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
