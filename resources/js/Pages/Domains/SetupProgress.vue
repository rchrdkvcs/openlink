<script setup lang="ts">
import { Check } from '@lucide/vue';

import { type SetupStep, setupSteps } from '@/lib/domains';

defineProps<{ step: SetupStep }>();
</script>

<template>
  <ol class="flex items-center gap-2" aria-label="Setup progress">
    <template v-for="(item, index) in setupSteps" :key="item.number">
      <li class="flex items-center gap-2" :aria-current="step === item.number ? 'step' : undefined">
        <span
          class="grid h-5 w-5 place-items-center rounded-full text-[11px] font-semibold transition-colors duration-200"
          :class="
            step > item.number
              ? 'bg-success text-white'
              : step === item.number
                ? 'bg-foreground text-background'
                : 'border border-border-strong text-faint'
          "
        >
          <Check v-if="step > item.number" class="h-3 w-3" />
          <template v-else>{{ item.number }}</template>
        </span>
        <span class="text-[13px]" :class="step >= item.number ? 'font-medium text-foreground' : 'text-faint'">
          {{ item.label }}
        </span>
      </li>
      <li v-if="index < setupSteps.length - 1" aria-hidden="true" class="h-px w-8 bg-border" />
    </template>
  </ol>
</template>
