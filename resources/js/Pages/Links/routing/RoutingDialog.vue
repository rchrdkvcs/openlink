<script setup lang="ts">
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { watch } from 'vue';

const props = withDefaults(defineProps<{ open: boolean; title: string; description?: string; alert?: boolean }>(), {
  alert: false,
});
const emit = defineEmits<{ 'update:open': [open: boolean]; closed: [] }>();
let trigger: HTMLElement | null = null;
watch(
  () => props.open,
  (open) => {
    if (open) trigger = document.activeElement as HTMLElement | null;
  },
  { flush: 'sync' },
);

function restoreFocus() {
  trigger?.focus({ preventScroll: true });
  emit('closed');
}
</script>

<template>
  <DialogRoot :open="open" @update:open="emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-[90] bg-black/60" />
      <DialogContent
        :role="alert ? 'alertdialog' : 'dialog'"
        class="fixed inset-x-0 top-[5dvh] z-[91] mx-auto flex max-h-[90dvh] w-[calc(100vw-2rem)] flex-col rounded-2xl bg-overlay shadow-dialog focus:outline-none"
        :class="alert ? 'max-w-sm' : 'max-w-3xl'"
        v-bind="description ? {} : { 'aria-describedby': undefined }"
        @close-auto-focus.prevent="restoreFocus"
      >
        <div class="shrink-0 px-5 pb-1 pt-5">
          <DialogTitle class="text-[15px] font-semibold">{{ title }}</DialogTitle>
          <DialogDescription v-if="description" class="mt-1 text-[13px] leading-relaxed text-muted">{{
            description
          }}</DialogDescription>
        </div>
        <slot />
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
