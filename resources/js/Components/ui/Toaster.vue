<script setup lang="ts">
import { CheckCircle2, CircleAlert, X } from '@lucide/vue';

import { dismissToast, pauseToast, resumeToast, toasts } from '@/lib/toast';
</script>

<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-[min(380px,calc(100vw-2rem))] flex-col gap-2"
      role="region"
      aria-label="Notifications"
      aria-live="polite"
    >
      <TransitionGroup
        enter-active-class="transition duration-300 ease-emphasized-out"
        enter-from-class="translate-y-2 scale-[0.98] opacity-0"
        enter-to-class="translate-y-0 scale-100 opacity-100"
        leave-active-class="absolute inset-x-0 transition duration-150 ease-out"
        leave-from-class="opacity-100"
        leave-to-class="translate-x-2 opacity-0"
        move-class="transition-transform duration-300 ease-emphasized-out"
      >
        <div
          v-for="item in toasts"
          :key="item.id"
          class="material pointer-events-auto flex items-start gap-3 rounded-xl py-3 pl-3.5 pr-2 shadow-dialog"
          @mouseenter="pauseToast(item.id)"
          @mouseleave="resumeToast(item.id)"
        >
          <CheckCircle2 v-if="item.tone === 'success'" class="mt-0.5 h-4 w-4 shrink-0 text-success" />
          <CircleAlert v-else-if="item.tone === 'danger'" class="mt-0.5 h-4 w-4 shrink-0 text-danger" />
          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-medium text-foreground">{{ item.title }}</p>
            <p v-if="item.description" class="mt-0.5 truncate font-mono text-xs text-muted">
              {{ item.description }}
            </p>
          </div>
          <button
            v-if="item.action"
            type="button"
            class="shrink-0 rounded-md px-2 py-1 text-[13px] font-medium text-accent transition-colors hover:bg-accent/10"
            @click="
              item.action.run();
              dismissToast(item.id);
            "
          >
            {{ item.action.label }}
          </button>
          <button
            type="button"
            class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-faint transition-colors hover:bg-elevated hover:text-foreground"
            aria-label="Dismiss"
            @click="dismissToast(item.id)"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
