<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, Plus, QrCode } from '@lucide/vue';
import { ref } from 'vue';
import CreateQrCodeDrawer from './CreateQrCodeDrawer.vue';
import type { PayloadDescriptors, QrCodeRecord } from './types';
import { payloadDefaults, payloadIcon } from './types';

const props = defineProps<{
    qrCodes: QrCodeRecord[];
    payloadTypes: Record<string, string>;
    payloadDescriptors: PayloadDescriptors;
    canEditWorkspace: boolean;
}>();

const createOpen = ref(false);

const form = useForm({
    name: '',
    payload_type: 'url',
    payload: payloadDefaults('url', props.payloadDescriptors),
});

function setPayloadType(type: string) {
    if (type === form.payload_type) {
        return;
    }
    form.payload_type = type;
    form.payload = payloadDefaults(type, props.payloadDescriptors);
    form.clearErrors();
}

function submit() {
    form.post(route('qr-codes.store-direct'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.payload = payloadDefaults('url', props.payloadDescriptors);
            createOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="QR Codes" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">QR Codes</h1>
                    <p class="mt-1 max-w-2xl text-sm text-muted">Scannable codes for web pages, Wi-Fi, contact cards, events and more.</p>
                </div>
                <Button v-if="canEditWorkspace" @click="createOpen = true">
                    <Plus class="h-4 w-4" />
                    New QR code
                </Button>
            </div>

            <EmptyState
                v-if="qrCodes.length === 0"
                title="No QR Codes yet"
                description="Create your first code — the exported image keeps working even when you change what it points to."
            >
                <template #icon><QrCode class="h-5 w-5 text-faint" /></template>
                <template v-if="canEditWorkspace" #action>
                    <Button @click="createOpen = true">
                        <Plus class="h-4 w-4" />
                        New QR code
                    </Button>
                </template>
            </EmptyState>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                <Link
                    v-for="qr in qrCodes"
                    :key="qr.id"
                    :href="route('qr-codes.show', qr.token)"
                    class="card-sheen group grid gap-0 overflow-hidden rounded-lg border bg-surface transition-colors hover:border-border-strong"
                >
                    <div class="relative grid place-items-center border-b bg-white p-6">
                        <img :src="route('qr-codes.preview', qr.token)" :alt="qr.name" class="h-32 w-32 object-contain" loading="lazy" />
                        <div
                            class="absolute inset-x-0 bottom-0 flex justify-center gap-1.5 bg-gradient-to-t from-black/40 to-transparent p-2 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100"
                        >
                            <a
                                v-for="format in ['svg', 'png']"
                                :key="format"
                                :href="route('qr-codes.export', [qr.token, format])"
                                class="inline-flex h-7 items-center gap-1 rounded-md bg-white/90 px-2 text-xs font-medium text-zinc-900 shadow hover:bg-white"
                                @click.stop
                            >
                                <Download class="h-3 w-3" /> {{ format.toUpperCase() }}
                            </a>
                        </div>
                    </div>
                    <div class="grid gap-1.5 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="truncate text-sm font-semibold text-foreground">{{ qr.name }}</h2>
                            <Badge class="inline-flex shrink-0 items-center gap-1">
                                <component :is="payloadIcon(qr.payload_type)" class="h-3 w-3" />
                                {{ payloadTypes[qr.payload_type] ?? qr.payload_type }}
                            </Badge>
                        </div>
                        <p class="truncate font-mono text-xs text-faint">{{ qr.content }}</p>
                    </div>
                </Link>
            </div>
        </div>

        <CreateQrCodeDrawer
            :show="createOpen"
            :form="form"
            :payload-types="payloadTypes"
            :payload-descriptors="payloadDescriptors"
            @close="createOpen = false"
            @set-type="setPayloadType"
            @submit="submit"
        />
    </AuthenticatedLayout>
</template>
