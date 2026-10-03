<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';

defineProps<{
  dirty: boolean;
  processing: boolean;
  hasErrors?: boolean;
}>();

const emit = defineEmits<{ discard: []; save: [] }>();
</script>

<template>
  <div class="pointer-events-none sticky bottom-4 z-20">
    <Transition
      enter-active-class="transition duration-200 ease-emphasized-out"
      enter-from-class="translate-y-2 opacity-0"
      leave-active-class="transition duration-150 ease-out"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="dirty || processing"
        class="material pointer-events-auto mx-auto flex w-full max-w-lg items-center justify-between gap-3 rounded-xl py-2 pl-4 pr-2 shadow-dialog"
      >
        <p class="truncate text-[13px]" :class="hasErrors ? 'text-danger' : 'text-muted'">
          {{ hasErrors ? 'Some fields need attention.' : 'You have unsaved changes.' }}
        </p>
        <div class="flex shrink-0 items-center gap-2">
          <Button variant="ghost" size="sm" type="button" :disabled="processing" @click="emit('discard')"
            >Discard</Button
          >
          <Button size="sm" type="button" :loading="processing" @click="emit('save')">Save changes</Button>
        </div>
      </div>
    </Transition>
  </div>
</template>
