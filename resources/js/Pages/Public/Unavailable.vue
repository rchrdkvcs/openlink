<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';

defineProps<{
    title: string;
    message: string;
}>();
</script>

<template>
    <Head :title="title" />

    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-4">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--warning)/0.08),transparent_65%)]" />

        <section class="card-sheen relative w-full max-w-sm animate-slide-up rounded-xl border bg-surface p-6 text-center shadow-2xl shadow-black/30">
            <div class="mx-auto mb-4 grid h-11 w-11 place-items-center rounded-xl border bg-elevated text-warning">
                <CircleAlert class="h-5 w-5" />
            </div>
            <h1 class="text-lg font-semibold text-foreground">{{ title }}</h1>
            <p class="mt-2 text-sm text-muted">{{ message }}</p>
        </section>
    </main>
</template>
