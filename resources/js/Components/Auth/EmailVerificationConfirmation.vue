<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';

const page = usePage();
const open = ref(false);

watch(
  () => page.flash,
  (flash) => {
    open.value = flash?.emailVerified === true;
  },
  { immediate: true },
);
</script>

<template>
  <Dialog
    v-model:open="open"
    title="Email verified"
    description="Your email address has been successfully verified. You're ready to use Openlink."
    size="sm"
  >
    <div class="flex items-center justify-between gap-4 px-5 pb-5 pt-4">
      <span class="grid h-10 w-10 place-items-center rounded-xl bg-success/10 text-success" aria-hidden="true">
        <MailCheck class="h-5 w-5" />
      </span>
      <Button type="button" @click="open = false">Continue</Button>
    </div>
  </Dialog>
</template>
