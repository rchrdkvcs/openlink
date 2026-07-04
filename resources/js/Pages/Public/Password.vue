<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';

const props = defineProps<{
    shortLinkId: number;
    qrCodeId?: number | null;
    error?: string | null;
}>();

const form = useForm({
    password: '',
    qr_code_id: props.qrCodeId ?? '',
});

function submit() {
    form.post(route('public.password', props.shortLinkId));
}
</script>

<template>
    <Head title="Protected link" />

    <main class="flex min-h-screen items-center justify-center bg-gray-100 px-4">
        <section class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center gap-3">
                <div class="rounded bg-gray-950 p-2 text-white">
                    <LockKeyhole class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-950">Protected link</h1>
                    <p class="text-sm text-gray-500">Enter the visitor password to continue.</p>
                </div>
            </div>

            <form class="grid gap-3" @submit.prevent="submit">
                <input
                    v-model="form.password"
                    type="password"
                    autofocus
                    class="rounded border-gray-300 text-sm"
                    placeholder="Password"
                />
                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
                <button class="rounded bg-gray-950 px-3 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Continue
                </button>
            </form>
        </section>
    </main>
</template>
