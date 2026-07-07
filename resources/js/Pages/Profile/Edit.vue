<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import TwoFactorForm from './Partials/TwoFactorForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
    twoFactor: {
        enabled: boolean;
        pendingSecret?: string | null;
        otpauthUrl?: string | null;
    };
}>();
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight">Profile</h1>
                <p class="mt-1 text-sm text-muted">Your account information and security settings.</p>
            </div>

            <div class="space-y-6">
                <div class="card-sheen rounded-lg border bg-surface p-5 sm:p-6">
                    <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="max-w-xl" />
                </div>

                <div class="card-sheen rounded-lg border bg-surface p-5 sm:p-6">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="card-sheen rounded-lg border bg-surface p-5 sm:p-6">
                    <TwoFactorForm :two-factor="twoFactor" class="max-w-xl" />
                </div>

                <div class="rounded-lg border border-danger/25 bg-surface p-5 sm:p-6">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
