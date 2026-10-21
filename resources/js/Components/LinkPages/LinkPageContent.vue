<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';

import type { PublicLinkPage } from './types';

defineProps<{
  page: PublicLinkPage;
}>();
</script>

<template>
  <article class="mx-auto flex w-full max-w-md flex-col items-center text-center">
    <img
      v-if="page.logo"
      :src="page.logo"
      alt=""
      class="mb-5 h-20 w-20 rounded-2xl border bg-elevated object-cover"
      width="80"
      height="80"
    />
    <h1 class="text-balance break-words text-xl font-semibold text-foreground">{{ page.title }}</h1>
    <p v-if="page.description" class="mt-2 text-pretty break-words text-sm leading-relaxed text-muted">
      {{ page.description }}
    </p>

    <ul v-if="page.links.length" class="mt-8 grid w-full gap-2.5" aria-label="Links">
      <li v-for="link in page.links" :key="link.url">
        <a
          :href="link.url"
          rel="noopener"
          class="group flex min-h-12 w-full items-center gap-3 rounded-xl border bg-surface px-4 py-3 text-left text-[15px] font-medium text-foreground transition-colors duration-150 hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50"
        >
          <span class="min-w-0 flex-1 break-words">{{ link.label }}</span>
          <ArrowUpRight
            class="h-4 w-4 shrink-0 text-faint transition-colors group-hover:text-foreground"
            aria-hidden="true"
          />
        </a>
      </li>
    </ul>
    <p v-else class="mt-8 w-full rounded-xl border border-dashed px-4 py-6 text-sm text-muted">
      No links are available right now.
    </p>
  </article>
</template>
