<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm<{ email?: string }>({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-foreground">Verify your email</h1>
            <p class="mt-1 text-sm text-muted">
                Thanks for signing up! Please verify your email address by clicking the link we just sent you. Didn't receive
                it? We will gladly send another.
            </p>
        </div>

        <div v-if="verificationLinkSent" class="mb-4 rounded-md border border-success/25 bg-success/10 px-3 py-2 text-sm text-success">
            A new verification link has been sent to the email address you provided during registration.
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <InputError :message="form.errors.email" />
            <PrimaryButton class="w-full" :disabled="form.processing">Resend verification email</PrimaryButton>
        </form>

        <template #footer>
            <p class="mt-6 text-center text-sm text-muted">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="font-medium text-foreground underline-offset-4 hover:underline"
                >
                    Log out
                </Link>
            </p>
        </template>
    </GuestLayout>
</template>
