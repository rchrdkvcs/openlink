<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

defineProps<{
    twoFactor: {
        enabled: boolean;
        pendingSecret?: string | null;
        otpauthUrl?: string | null;
    };
}>();

const prepareForm = useForm({});
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });

function prepare() {
    prepareForm.post(route('profile.two-factor.prepare'), { preserveScroll: true });
}

function confirm() {
    confirmForm.post(route('profile.two-factor.confirm'), { preserveScroll: true, onSuccess: () => confirmForm.reset() });
}

function disable() {
    disableForm.delete(route('profile.two-factor.disable'), { preserveScroll: true, onSuccess: () => disableForm.reset() });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Two-Factor Authentication</h2>
            <p class="mt-1 text-sm text-gray-600">Protect your account with a time-based one-time password app.</p>
        </header>

        <div class="mt-6 space-y-5">
            <div v-if="twoFactor.enabled" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                Two-factor authentication is enabled.
            </div>

            <div v-else-if="twoFactor.pendingSecret" class="space-y-4">
                <div class="rounded border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-900">Add this secret to your authenticator app, then enter a code to confirm.</p>
                    <code class="mt-3 block break-all rounded bg-white px-3 py-2 text-sm text-gray-900">{{ twoFactor.pendingSecret }}</code>
                    <p v-if="twoFactor.otpauthUrl" class="mt-2 break-all text-xs text-amber-800">{{ twoFactor.otpauthUrl }}</p>
                </div>

                <form class="flex max-w-sm items-end gap-3" @submit.prevent="confirm">
                    <div class="flex-1">
                        <InputLabel for="two_factor_code" value="Authentication code" />
                        <TextInput id="two_factor_code" v-model="confirmForm.code" class="mt-1 block w-full" inputmode="numeric" />
                        <InputError :message="confirmForm.errors.code" class="mt-2" />
                    </div>
                    <PrimaryButton :disabled="confirmForm.processing">Confirm</PrimaryButton>
                </form>
            </div>

            <div v-else>
                <SecondaryButton :disabled="prepareForm.processing" @click="prepare">Set up 2FA</SecondaryButton>
            </div>

            <form v-if="twoFactor.enabled" class="flex max-w-sm items-end gap-3" @submit.prevent="disable">
                <div class="flex-1">
                    <InputLabel for="disable_two_factor_password" value="Current password" />
                    <TextInput id="disable_two_factor_password" v-model="disableForm.password" type="password" class="mt-1 block w-full" />
                    <InputError :message="disableForm.errors.password" class="mt-2" />
                </div>
                <DangerButton :disabled="disableForm.processing">Disable</DangerButton>
            </form>
        </div>
    </section>
</template>
