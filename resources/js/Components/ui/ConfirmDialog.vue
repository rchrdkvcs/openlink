<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
} from 'radix-vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import type { Confirmation } from '@/lib/useConfirmation';
import { useDialogFocus } from '@/lib/useDialogFocus';

const props = defineProps<{ confirmation: Confirmation | null }>();
const emit = defineEmits<{ close: [] }>();
const restoreFocus = useDialogFocus(computed(() => props.confirmation !== null));
</script>
<template>
  <AlertDialogRoot :open="confirmation !== null" @update:open="!$event && emit('close')">
    <AlertDialogPortal>
      <AlertDialogOverlay class="ui-overlay fixed inset-0 z-[80]" />
      <AlertDialogContent
        class="ui-dialog fixed left-1/2 top-1/2 z-[80] w-[calc(100%-2rem)] max-w-md p-6 outline-none"
        @close-auto-focus="restoreFocus"
      >
        <span class="mb-4 grid h-9 w-9 place-items-center rounded-xl bg-danger/10 text-danger"
          ><AlertTriangle class="h-4 w-4"
        /></span>
        <AlertDialogTitle class="break-words text-base font-semibold [overflow-wrap:anywhere]">{{
          confirmation?.title
        }}</AlertDialogTitle>
        <AlertDialogDescription class="mt-2 break-words text-[13px] leading-relaxed text-muted">{{
          confirmation?.description
        }}</AlertDialogDescription>
        <div class="mt-6 flex justify-end gap-2">
          <AlertDialogCancel as-child><Button variant="secondary" type="button">Cancel</Button></AlertDialogCancel>
          <AlertDialogAction as-child
            ><Button variant="danger" type="button" @click="confirmation?.action()">Delete</Button></AlertDialogAction
          >
        </div>
      </AlertDialogContent>
    </AlertDialogPortal>
  </AlertDialogRoot>
</template>
