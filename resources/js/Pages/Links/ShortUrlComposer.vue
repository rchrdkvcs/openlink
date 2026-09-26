<script setup lang="ts">
import { Dices } from '@lucide/vue';
import { computed } from 'vue';

import Select from '@/Components/ui/Select.vue';
import { randomSlug } from '@/lib/links';

import type { Domain } from './types';

const props = defineProps<{
  domainId: number | string;
  slug: string;
  domains: Domain[];
  slugPlaceholder?: string;
}>();

const emit = defineEmits<{ 'update:domainId': [value: number | string]; 'update:slug': [value: string] }>();

const domainOptions = computed(() => props.domains.map((domain) => ({ value: domain.id, label: domain.hostname })));
</script>

<template>
  <div
    class="flex items-stretch overflow-hidden rounded-xl border bg-surface transition-colors focus-within:border-accent/60 focus-within:ring-2 focus-within:ring-accent/25"
  >
    <Select
      :model-value="domainId"
      :options="domainOptions"
      class="h-11 w-auto max-w-[45%] rounded-none border-0 border-r border-r-border bg-elevated/50 text-[13px] font-medium shadow-none focus:ring-0 focus-visible:ring-0"
      @update:model-value="emit('update:domainId', $event)"
    />
    <span class="grid place-items-center px-2 font-mono text-sm text-faint">/</span>
    <input
      :value="slug"
      class="h-11 min-w-0 flex-1 bg-transparent font-mono text-sm text-foreground outline-none placeholder:text-faint"
      :placeholder="slugPlaceholder ?? 'auto-generated'"
      spellcheck="false"
      @input="emit('update:slug', ($event.target as HTMLInputElement).value)"
    />
    <button
      type="button"
      class="grid w-11 shrink-0 place-items-center border-l text-faint transition-colors hover:bg-elevated hover:text-foreground"
      title="Random slug"
      @click="emit('update:slug', randomSlug())"
    >
      <Dices class="h-4 w-4" />
    </button>
  </div>
</template>
