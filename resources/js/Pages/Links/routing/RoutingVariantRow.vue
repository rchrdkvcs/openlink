<script setup lang="ts">
import { X } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Switch from '@/Components/ui/Switch.vue';
import type { RoutingVariantDraft } from '@/types/shortLinks';

defineProps<{ variant: RoutingVariantDraft; position: number; share: number; tone: string }>();

const emit = defineEmits<{ remove: [] }>();
</script>

<template>
  <div
    class="@xl:grid-cols-[7rem_minmax(0,1fr)_4.5rem_4rem_2.25rem_1.75rem] grid grid-cols-[minmax(0,1fr)_4.5rem_4rem_2.25rem_1.75rem] items-center gap-x-2 gap-y-1.5"
  >
    <Input
      v-model="variant.name"
      :class="!variant.is_enabled && 'opacity-50'"
      :aria-label="`Variant ${position} name`"
    />
    <Input
      v-model="variant.destination_url"
      type="url"
      spellcheck="false"
      class="@xl:col-span-1 @xl:col-start-2 @xl:row-start-1 col-span-4 row-start-2"
      :class="!variant.is_enabled && 'opacity-50'"
      placeholder="https://example.com/variant"
      :aria-label="`Variant ${variant.name} destination URL`"
    />
    <Input
      v-model="variant.weight"
      type="number"
      min="1"
      step="1"
      class="tabular-nums"
      :class="!variant.is_enabled && 'opacity-50'"
      :aria-label="`Variant ${variant.name} weight`"
    />
    <span
      class="inline-flex h-6 items-center gap-1.5 justify-self-end rounded-full bg-elevated px-2 font-mono text-[11px] tabular-nums text-muted"
      :class="!variant.is_enabled && 'opacity-50'"
      :aria-label="`${share}% of matched traffic`"
    >
      <span class="h-1.5 w-1.5 rounded-full" :class="tone" aria-hidden="true" />
      {{ share }}%
    </span>
    <Switch v-model="variant.is_enabled" class="justify-self-center" :aria-label="`Enable variant ${variant.name}`" />
    <Button
      type="button"
      variant="ghost"
      size="sm"
      class="w-7 px-0 text-faint hover:text-danger"
      :aria-label="`Remove variant ${variant.name}`"
      @click="emit('remove')"
      ><X
    /></Button>
  </div>
</template>
