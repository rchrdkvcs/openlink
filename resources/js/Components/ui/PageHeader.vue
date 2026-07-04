<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

defineProps<{
    section: string;
}>();

const page = usePage();
const workspaceName = computed(() => (page.props.currentWorkspace as { name?: string } | undefined)?.name);
</script>

<template>
    <div class="flex min-w-0 items-center justify-between gap-4">
        <nav class="flex min-w-0 items-center gap-1.5 text-[13px]">
            <span v-if="workspaceName" class="hidden truncate text-faint sm:block">{{ workspaceName }}</span>
            <ChevronRight v-if="workspaceName" class="hidden h-3.5 w-3.5 shrink-0 text-faint/60 sm:block" />
            <span class="truncate font-medium text-foreground">{{ section }}</span>
        </nav>
        <div class="flex shrink-0 items-center gap-2">
            <slot />
        </div>
    </div>
</template>
