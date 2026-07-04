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

    <main class="flex min-h-screen items-center justify-center bg-gray-100 px-4">
        <section class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded bg-gray-950 text-white">
                <CircleAlert class="h-6 w-6" />
            </div>
            <h1 class="text-xl font-semibold text-gray-950">{{ title }}</h1>
            <p class="mt-2 text-sm text-gray-500">{{ message }}</p>
        </section>
    </main>
</template>
