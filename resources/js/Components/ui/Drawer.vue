<script setup lang="ts">
import { X } from '@lucide/vue';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { toRef } from 'vue';

import { useDialogFocus } from '@/lib/useDialogFocus';

const props = withDefaults(defineProps<{ show?: boolean; eyebrow?: string; title?: string }>(), { show: false });
const emit = defineEmits<{ close: [] }>();
const restoreFocus = useDialogFocus(toRef(props, 'show'));
</script>

<template>
  <DialogRoot :open="show" @update:open="!$event && emit('close')">
    <DialogPortal>
      <DialogOverlay class="ui-overlay fixed inset-0 z-50" />
      <DialogContent
        :aria-describedby="undefined"
        class="ui-drawer fixed inset-y-2 end-2 z-50 flex w-[calc(100%-1rem)] max-w-xl flex-col overflow-hidden outline-none sm:inset-y-3 sm:end-3"
        @close-auto-focus="restoreFocus"
      >
        <DialogTitle class="sr-only">{{ title ?? eyebrow ?? 'Details' }}</DialogTitle>
        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-border/70 px-5 py-4">
          <slot name="header">
            <div class="min-w-0">
              <p v-if="eyebrow" class="mb-1 text-xs text-muted">{{ eyebrow }}</p>
              <h2 class="truncate text-sm font-semibold text-foreground">{{ title }}</h2>
            </div>
          </slot>
          <DialogClose class="ui-icon-button" aria-label="Close panel"><X class="h-4 w-4" /></DialogClose>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain"><slot /></div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
