<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';

defineProps<{
  visible: boolean;
  processing: boolean;
  hasErrors: boolean;
}>();

const emit = defineEmits<{ save: []; discard: [] }>();
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-emphasized-out"
    enter-from-class="translate-y-full"
    leave-active-class="transition duration-150 ease-out"
    leave-to-class="translate-y-full"
  >
    <div v-if="visible" class="flex items-center gap-2 border-t bg-overlay px-5 py-3">
      <p class="min-w-0 flex-1 truncate text-[13px]" :class="hasErrors ? 'text-danger' : 'text-muted'">
        {{ hasErrors ? 'Some fields need attention.' : 'Unsaved changes' }}
      </p>
      <Button variant="ghost" size="sm" type="button" :disabled="processing" @click="emit('discard')">Discard</Button>
      <Button size="sm" type="button" :loading="processing" @click="emit('save')">Save</Button>
    </div>
  </Transition>
</template>
