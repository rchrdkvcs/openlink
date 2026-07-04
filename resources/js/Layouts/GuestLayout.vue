<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-4 py-12">
        <!-- Ambient glow -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.12),transparent_65%)]" />

        <div class="relative w-full max-w-sm animate-slide-up">
            <div class="mb-8 flex flex-col items-center gap-3">
                <Link
                    href="/"
                    class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-accent to-accent/60 text-white shadow-lg shadow-accent/20"
                >
                    <ApplicationLogo class="h-5 w-5" />
                </Link>
                <span class="text-sm font-semibold tracking-wide text-foreground">Openlink</span>
            </div>

            <div class="card-sheen rounded-xl border bg-surface p-6 shadow-2xl shadow-black/30">
                <slot />
            </div>

            <slot name="footer" />
        </div>
    </div>
</template>
