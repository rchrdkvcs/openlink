<script setup lang="ts">
import { ref } from 'vue';

import { useSidebar } from '@/Components/ui/sidebar';

const width = defineModel<number>({ required: true });
const emit = defineEmits<{ resizing: [value: boolean] }>();
const { open, isMobile } = useSidebar();
const dragging = ref(false);
let startX = 0;
let startWidth = 0;

function resize(value: number) {
  width.value = Math.min(384, Math.max(208, value));
}

function start(event: PointerEvent) {
  if (event.button !== 0) return;
  startX = event.clientX;
  startWidth = width.value;
  (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
  dragging.value = true;
  emit('resizing', true);
}

function move(event: PointerEvent) {
  if (dragging.value) resize(startWidth + event.clientX - startX);
}

function finish() {
  dragging.value = false;
  emit('resizing', false);
}

function keydown(event: KeyboardEvent) {
  const values: Record<string, number> = {
    ArrowLeft: width.value - 16,
    ArrowRight: width.value + 16,
    Home: 208,
    End: 384,
  };
  const value = values[event.key];
  if (value === undefined) return;
  event.preventDefault();
  resize(value);
}
</script>

<template>
  <div
    v-if="open && !isMobile"
    role="separator"
    aria-label="Sidebar width"
    aria-orientation="vertical"
    :aria-valuenow="width"
    :aria-valuemin="208"
    :aria-valuemax="384"
    tabindex="0"
    title="Drag to resize · Double-click to reset"
    class="absolute inset-y-0 -right-1 z-30 w-2 cursor-col-resize touch-none outline-none after:absolute after:inset-y-3 after:left-1/2 after:w-px after:-translate-x-1/2 after:rounded-full hover:after:bg-border-strong focus-visible:after:bg-accent"
    :class="dragging ? 'after:bg-accent' : ''"
    @pointerdown.prevent="start"
    @pointermove="move"
    @pointerup="finish"
    @pointercancel="finish"
    @lostpointercapture="finish"
    @keydown="keydown"
    @dblclick="width = 240"
  />
</template>
