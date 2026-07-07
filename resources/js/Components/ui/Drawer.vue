<script setup lang="ts">
import IconButton from '@/Components/ui/IconButton.vue';
import { X } from '@lucide/vue';
import { onMounted, onUnmounted, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        show: boolean;
        eyebrow?: string;
        title?: string;
    }>(),
    { show: false },
);

const emit = defineEmits<{ close: [] }>();

watch(
    () => props.show,
    (show) => {
        document.body.style.overflow = show ? 'hidden' : '';
    },
);

const closeOnEscape = (e: KeyboardEvent) => {
    if (props.show && e.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 bg-background/70 backdrop-blur-[2px]" @click="emit('close')" />
        </Transition>

        <Transition
            enter-active-class="transition-transform ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform ease-in duration-200"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="show"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col border-l bg-overlay shadow-drawer"
                role="dialog"
                aria-modal="true"
            >
                <header class="flex shrink-0 items-center justify-between gap-4 border-b px-5 py-4">
                    <slot name="header">
                        <div class="min-w-0">
                            <p v-if="eyebrow" class="text-xs font-medium uppercase tracking-wide text-faint">{{ eyebrow }}</p>
                            <h3 class="truncate text-[15px] font-semibold text-foreground">{{ title }}</h3>
                        </div>
                    </slot>
                    <IconButton title="Close" @click="emit('close')">
                        <X class="h-4 w-4" />
                    </IconButton>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <slot />
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
