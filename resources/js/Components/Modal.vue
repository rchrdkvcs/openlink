<script setup lang="ts">
import { X } from '@lucide/vue';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { computed, toRef } from 'vue';

import { useDialogFocus } from '@/lib/useDialogFocus';

const props = withDefaults(
  defineProps<{
    show?: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    closeable?: boolean;
    title?: string;
  }>(),
  { show: false, maxWidth: '2xl', closeable: true, title: 'Dialog' },
);
const emit = defineEmits<{ close: [] }>();
const restoreFocus = useDialogFocus(toRef(props, 'show'));
const width = computed(
  () => ({ sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl', '2xl': 'max-w-2xl' })[props.maxWidth],
);
function dismiss(open: boolean) {
  if (!open && props.closeable) emit('close');
}
function preventDismiss(event: Event) {
  if (!props.closeable) event.preventDefault();
}
</script>

<template>
  <DialogRoot :open="show" @update:open="dismiss">
    <DialogPortal>
      <DialogOverlay class="ui-overlay fixed inset-0 z-50" />
      <DialogContent
        :aria-describedby="undefined"
        :class="width"
        class="ui-dialog fixed left-1/2 top-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto overscroll-contain outline-none"
        @escape-key-down="preventDismiss"
        @interact-outside="preventDismiss"
        @close-auto-focus="restoreFocus"
      >
        <DialogTitle class="sr-only">{{ title }}</DialogTitle>
        <DialogClose v-if="closeable" aria-label="Close dialog" class="ui-icon-button absolute end-3 top-3 z-10"
          ><X class="h-4 w-4"
        /></DialogClose>
        <slot />
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
