<script setup lang="ts">
import { nextTick, onBeforeMount, onUnmounted, ref } from 'vue';

import Dialog from '@/Components/ui/Dialog.vue';
import type { RoutingSchema, ShortLink } from '@/types/shortLinks';

import RoutingEditor from './RoutingEditor.vue';

defineProps<{ link: ShortLink; routingSchema: RoutingSchema; inspectorDirty: boolean }>();
const emit = defineEmits<{ close: []; settled: [] }>();
const editor = ref<InstanceType<typeof RoutingEditor> | null>(null);
let trigger: HTMLElement | null = null;

onBeforeMount(() => {
  trigger = document.activeElement as HTMLElement | null;
});
onUnmounted(() => nextTick(() => trigger?.focus({ preventScroll: true })));
</script>

<template>
  <Dialog
    :open="true"
    label="Smart routing"
    class="top-[2dvh] flex h-[96dvh] max-w-[1440px] flex-col overflow-hidden"
    @update:open="!$event && editor?.requestClose()"
  >
    <RoutingEditor
      ref="editor"
      :link="link"
      :routing-schema="routingSchema"
      :inspector-dirty="inspectorDirty"
      class="h-full"
      @close="emit('close')"
      @settled="emit('settled')"
    />
  </Dialog>
</template>
