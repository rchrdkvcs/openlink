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

    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-4">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.12),transparent_65%)]" />

        <section class="card-sheen relative w-full max-w-sm animate-slide-up rounded-xl border bg-surface p-6 shadow-2xl shadow-black/30">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <div class="grid h-11 w-11 place-items-center rounded-xl border bg-elevated text-muted">
                    <LockKeyhole class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-foreground">Protected link</h1>
                    <p class="mt-1 text-sm text-muted">Enter the visitor password to continue.</p>
                </div>
            </div>

            <form class="grid gap-3" @submit.prevent="submit">
                <input v-model="form.password" type="password" autofocus class="h-10" placeholder="Password" />
                <p v-if="error" class="text-sm text-danger">{{ error }}</p>
                <button
                    class="inline-flex h-10 items-center justify-center rounded-md bg-foreground px-4 text-sm font-medium text-background transition-colors duration-150 hover:bg-foreground/85 disabled:pointer-events-none disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Continue
                </button>
            </form>
        </section>
    </main>
</template>
