<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, RotateCw } from '@lucide/vue';
import { computed } from 'vue';

import PublicStateLayout from '@/Components/PublicState/PublicStateLayout.vue';
import StateArt from '@/Components/PublicState/StateArt.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import { errorState } from '@/lib/publicStates';

const props = defineProps<{
  status: number;
}>();

const state = computed(() => errorState(props.status));

function retry() {
  window.location.reload();
}
</script>

<template>
  <Head :title="state.title" />

  <PublicStateLayout :title="state.title" :description="state.description" :tone="state.tone">
    <template #art>
      <StateArt :scene="state.scene" :tone="state.tone" />
    </template>

    <template #badge>
      <Badge variant="outline" class="font-mono tabular-nums">Error {{ status }}</Badge>
    </template>

    <div class="grid gap-2 sm:flex sm:justify-center">
      <Button v-if="state.canRetry" size="lg" type="button" @click="retry"><RotateCw /> Try again</Button>
      <a
        href="/"
        class="inline-flex h-10 select-none items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-4 text-sm font-medium transition-[color,background-color,transform] duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 active:scale-[0.96] [&_svg]:h-3.5 [&_svg]:w-3.5"
        :class="
          state.canRetry
            ? 'bg-elevated text-foreground hover:bg-border-strong'
            : 'bg-foreground text-background hover:bg-foreground/90'
        "
      >
        <ArrowLeft /> Back to Openlink
      </a>
    </div>
  </PublicStateLayout>
</template>
