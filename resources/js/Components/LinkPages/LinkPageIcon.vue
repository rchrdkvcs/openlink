<script setup lang="ts">
import { Globe } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ icon: string | null }>();

const icons = import.meta.glob<string>('./favicons/*.png', { eager: true, query: '?url', import: 'default' });
const src = computed(() => icons[`./favicons/${props.icon}.png`] ?? null);
</script>

<template>
  <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-elevated" aria-hidden="true">
    <img v-if="src" :src="src" alt="" class="h-5 w-5 object-contain" width="20" height="20" />
    <Globe v-else class="h-5 w-5 text-muted" />
  </span>
</template>
