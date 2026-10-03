<script setup lang="ts">
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import { pendingConfirm, settleConfirm } from '@/lib/confirm';

const open = computed(() => pendingConfirm.value !== null);
</script>

<template>
  <Dialog
    :open="open"
    size="sm"
    role="alertdialog"
    :title="pendingConfirm?.title"
    :description="pendingConfirm?.message"
    @update:open="!$event && settleConfirm(false)"
  >
    <div class="flex justify-end gap-2 px-5 pb-5 pt-4">
      <Button variant="secondary" type="button" @click="settleConfirm(false)">Cancel</Button>
      <Button :variant="pendingConfirm?.destructive ? 'danger' : 'primary'" type="button" @click="settleConfirm(true)">
        {{ pendingConfirm?.confirmLabel ?? 'Confirm' }}
      </Button>
    </div>
  </Dialog>
</template>
