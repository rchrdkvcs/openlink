<script setup lang="ts">
import { computed } from 'vue';

import Kbd from '@/Components/ui/Kbd.vue';
import Select from '@/Components/ui/Select.vue';
import type { Domain, Folder } from '@/types/payloads';

import ShortUrlComposer from './ShortUrlComposer.vue';

const props = defineProps<{ domains: Domain[]; folders: Folder[]; error?: string }>();

const domainId = defineModel<number | string>('domainId', { required: true });
const slug = defineModel<string>('slug', { required: true });
const folderId = defineModel<string>('folderId', { required: true });

const folderOptions = computed(() => [
  { value: '', label: 'No folder' },
  ...props.folders.map((folder) => ({ value: String(folder.id), label: folder.name })),
]);
</script>

<template>
  <div class="flex flex-wrap items-center gap-1.5 border-t px-2.5 py-2">
    <ShortUrlComposer
      v-model:domain-id="domainId"
      v-model:slug="slug"
      :domains="domains"
      size="sm"
      slug-label="Custom slug"
      slug-placeholder="auto"
      class="min-w-0 flex-1 sm:max-w-sm"
    />
    <Select
      v-if="folders.length"
      v-model="folderId"
      :options="folderOptions"
      size="sm"
      aria-label="Folder"
      class="w-auto min-w-32 max-w-48"
    />
    <p v-if="error" class="basis-full px-1 text-xs text-danger">{{ error }}</p>
    <p v-else-if="domains.length === 0" class="basis-full px-1 text-xs text-warning">
      Add and verify a domain before creating links.
    </p>
    <p v-else class="ml-auto hidden items-center gap-1.5 text-xs text-faint lg:flex">
      <Kbd>↵</Kbd> to shorten · <Kbd>Esc</Kbd> to clear
    </p>
  </div>
</template>
