<script setup lang="ts">
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';

import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    role?: 'dialog' | 'alertdialog';
    label?: string;
  }>(),
  { size: 'md', role: 'dialog' },
);

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const widthClass = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-xl',
  xl: 'max-w-3xl',
};
</script>

<template>
  <DialogRoot :open="props.open" @update:open="emit('update:open', $event)">
    <DialogPortal>
      <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150 ease-out"
        leave-to-class="opacity-0"
      >
        <DialogOverlay v-if="props.open" class="fixed inset-0 z-[80] bg-black/60 backdrop-blur-[2px]" />
      </Transition>
      <Transition
        enter-active-class="transition duration-200 ease-emphasized-out"
        enter-from-class="translate-y-1 scale-[0.97] opacity-0"
        leave-active-class="transition duration-150 ease-out"
        leave-to-class="scale-[0.98] opacity-0"
      >
        <DialogContent
          v-if="props.open"
          :role="role"
          :class="
            cn(
              'fixed inset-x-0 top-[14vh] z-[81] mx-auto w-[calc(100vw-2rem)] rounded-2xl bg-overlay shadow-dialog focus:outline-none',
              widthClass[size],
              $attrs.class as string,
            )
          "
          v-bind="description ? {} : { 'aria-describedby': undefined }"
        >
          <div v-if="title" class="px-5 pb-1 pt-5">
            <DialogTitle class="text-[15px] font-semibold text-foreground">{{ title }}</DialogTitle>
            <DialogDescription v-if="description" class="mt-1 text-[13px] leading-relaxed text-muted">
              {{ description }}
            </DialogDescription>
          </div>
          <DialogTitle v-if="!title" class="sr-only">{{ label ?? 'Dialog' }}</DialogTitle>
          <slot />
        </DialogContent>
      </Transition>
    </DialogPortal>
  </DialogRoot>
</template>
