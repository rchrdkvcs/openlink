<script setup lang="ts">
import { PanelLeft } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { cn } from '@/lib/utils';

import { useSidebar } from './utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();

const { toggleSidebar, open, openMobile, isMobile } = useSidebar();
</script>

<template>
  <Button
    data-sidebar="trigger"
    :aria-expanded="isMobile ? openMobile : open"
    aria-label="Toggle sidebar"
    title="Toggle sidebar (⌘/Ctrl+B)"
    variant="ghost"
    type="button"
    :class="cn('h-8 w-8 p-0', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeft />
    <span class="sr-only">Toggle Sidebar</span>
  </Button>
</template>
