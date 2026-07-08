<script setup lang="ts">
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import { Head } from '@inertiajs/vue3';
import { QrCode } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
    name: string;
    payloadType: string;
    payloadTypeLabel: string;
    content: string;
}>();

const copied = ref(false);

async function copyContent() {
    try {
        await navigator.clipboard.writeText(props.content);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        // Clipboard unavailable in insecure contexts.
    }
}
</script>

<template>
    <Head :title="name" />

    <main class="min-h-screen bg-background px-4 py-8 text-foreground">
        <section class="mx-auto grid w-full max-w-2xl animate-slide-up gap-5">
            <div class="text-center">
                <div class="mx-auto mb-4 grid h-11 w-11 place-items-center rounded-xl border bg-elevated text-muted">
                    <QrCode class="h-5 w-5" />
                </div>
                <p class="text-xs font-medium uppercase tracking-wide text-faint">{{ payloadTypeLabel }}</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight">{{ name }}</h1>
            </div>

            <section class="card-sheen overflow-hidden rounded-lg border bg-surface">
                <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <p class="truncate text-sm font-medium text-foreground">Current payload</p>
                    <button
                        type="button"
                        class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border px-2.5 text-[13px] font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                        @click="copyContent"
                    >
                        <CopyCheckIcon :copied="copied" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </button>
                </div>

                <pre class="max-h-[70vh] overflow-auto whitespace-pre-wrap break-words p-4 font-mono text-sm text-muted">{{ content }}</pre>
            </section>

            <p v-if="payloadType === 'wifi'" class="text-center text-sm text-muted">
                Copy this Wi-Fi payload into a QR scanner or network setup tool if your device does not apply it automatically.
            </p>
        </section>
    </main>
</template>
