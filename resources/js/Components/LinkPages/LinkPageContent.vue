<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';

import { displayUrl } from '@/lib/links';

import LinkPageIcon from './LinkPageIcon.vue';
import type { PublicLinkPage } from './types';

const props = defineProps<{
  page: PublicLinkPage;
}>();

const iconsOnly = computed(() => !props.page.display.show_labels && !props.page.display.show_urls);
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
    <h1 class="max-w-full text-balance text-xl font-semibold text-foreground [overflow-wrap:anywhere]">
      {{ page.title }}
    </h1>
    <p
      v-if="page.description"
      class="mt-2 max-w-full text-pretty text-sm leading-relaxed text-muted [overflow-wrap:anywhere]"
    >
      {{ page.description }}
    </p>

    <ul
      v-if="page.links.length"
      class="mt-8 w-full gap-3"
      :class="iconsOnly ? 'flex flex-wrap justify-center' : 'grid'"
      aria-label="Links"
    >
      <li v-for="link in page.links" :key="link.url">
        <a
          :href="link.url"
          :aria-label="
            page.display.show_urls && !page.display.show_labels ? `${link.label} (${displayUrl(link.url)})` : link.label
          "
          :title="iconsOnly ? link.label : undefined"
          rel="noopener"
          class="group flex min-h-14 items-center gap-3 rounded-xl border bg-surface text-left text-[15px] font-medium text-foreground transition-colors duration-150 hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background"
          :class="iconsOnly ? 'w-14 justify-center p-2' : 'w-full px-4 py-3'"
        >
          <LinkPageIcon v-if="page.display.show_icons || iconsOnly" :icon="link.icon" />
          <span v-if="!iconsOnly" class="min-w-0 flex-1 [overflow-wrap:anywhere]">
            <span v-if="page.display.show_labels" class="block">{{ link.label }}</span>
            <span v-if="page.display.show_urls" class="block text-xs font-normal text-muted">{{
              displayUrl(link.url)
            }}</span>
          </span>
          <ArrowUpRight
            v-if="!iconsOnly"
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
