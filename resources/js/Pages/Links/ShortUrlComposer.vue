<script setup lang="ts">
import { Dices } from '@lucide/vue';

import Select from '@/Components/ui/Select.vue';
import SelectOption from '@/Components/ui/SelectOption.vue';
import { randomSlug } from '@/lib/links';

import type { Domain } from './types';

defineProps<{
  domainId: number | string;
  slug: string;
  domains: Domain[];
  slugPlaceholder?: string;
}>();

const emit = defineEmits<{ 'update:domainId': [value: number | string]; 'update:slug': [value: string] }>();
</script>

<template>
  <div
    class="flex items-stretch overflow-hidden rounded-lg border bg-background/50 transition-colors focus-within:border-accent/60 focus-within:ring-2 focus-within:ring-accent/25"
  >
    <Select
      :model-value="domainId"
      aria-label="Short URL domain"
      class="h-9 w-auto max-w-[45%] !rounded-none !border-0 !border-r !border-r-border !bg-transparent !text-[13px] font-medium !shadow-none !ring-0"
      @update:model-value="emit('update:domainId', Number(String($event)))"
    >
      <SelectOption v-for="domain in domains" :key="domain.id" :value="domain.id">{{ domain.hostname }}</SelectOption>
    </Select>
    <span class="grid place-items-center px-2 font-mono text-sm text-faint">/</span>
    <input
      :value="slug"
      class="h-9 min-w-0 flex-1 !border-0 !bg-transparent !p-0 font-mono !text-sm !shadow-none !ring-0"
      :placeholder="slugPlaceholder ?? 'auto-generated'"
      spellcheck="false"
      @input="emit('update:slug', ($event.target as HTMLInputElement).value)"
    />
    <button
      type="button"
      class="grid w-9 shrink-0 place-items-center border-l text-faint transition-colors hover:bg-elevated hover:text-foreground"
      title="Random slug"
      @click="emit('update:slug', randomSlug())"
    >
      <Dices class="h-4 w-4" />
    </button>
  </div>
</template>
