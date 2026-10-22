<script setup lang="ts">
import { ArrowDown, ArrowUp, X } from '@lucide/vue';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Input from '@/Components/ui/Input.vue';
import { displayUrl } from '@/lib/links';

import { availabilityLabel, type EditableItem } from './linkPageItems';

const props = defineProps<{
  item: EditableItem;
  index: number;
  count: number;
  error?: string;
}>();

const emit = defineEmits<{ move: [offset: -1 | 1]; remove: []; label: [value: string] }>();

const shortUrl = computed(() => displayUrl(props.item.short_url));
</script>

<template>
  <li class="grid gap-2 rounded-xl border bg-surface p-3">
    <div class="flex items-center gap-2">
      <span class="min-w-0 flex-1">
        <span class="block truncate text-[13px] font-medium text-foreground">{{ shortUrl }}</span>
        <span class="block truncate text-xs text-faint">{{ displayUrl(item.destination_url) }}</span>
      </span>
      <Badge :variant="item.availability === 'active' ? 'success' : 'warning'">
        {{ availabilityLabel(item.availability) }}
      </Badge>
      <IconButton size="sm" :title="`Move ${shortUrl} up`" :disabled="index === 0" @click="emit('move', -1)">
        <ArrowUp class="h-3.5 w-3.5" />
      </IconButton>
      <IconButton size="sm" :title="`Move ${shortUrl} down`" :disabled="index === count - 1" @click="emit('move', 1)">
        <ArrowDown class="h-3.5 w-3.5" />
      </IconButton>
      <IconButton size="sm" variant="danger" :title="`Remove ${shortUrl}`" @click="emit('remove')">
        <X class="h-3.5 w-3.5" />
      </IconButton>
    </div>
    <Input
      :model-value="item.label"
      size="sm"
      maxlength="80"
      :placeholder="shortUrl"
      :aria-label="`Label for ${shortUrl}`"
      @update:model-value="emit('label', String($event ?? ''))"
    />
    <p v-if="error" class="text-xs text-danger">{{ error }}</p>
  </li>
</template>
