<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LayoutList, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { displayUrl } from '@/lib/links';
import { useShell } from '@/lib/shell';

import CreateLinkPageDialog from './CreateLinkPageDialog.vue';
import type { LinkPageSummary } from './types';

const props = defineProps<{
  linkPages: LinkPageSummary[];
  pathPrefix: string;
}>();

const { canEdit } = useShell();
const createOpen = ref(false);

const description = computed(() => {
  const count = props.linkPages.length;
  if (count === 0) return 'Share one public page that lists several short links.';

  return `${count} ${count === 1 ? 'page' : 'pages'}`;
});

function linkCount(count: number) {
  return `${count} ${count === 1 ? 'link' : 'links'}`;
}
</script>

<template>
  <Head title="Link pages" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <PageHeader title="Link pages" :description="description">
        <template v-if="canEdit" #actions>
          <Button size="sm" type="button" @click="createOpen = true"> <Plus class="h-4 w-4" /> New link page </Button>
        </template>
      </PageHeader>

      <div v-if="linkPages.length === 0" class="mt-6 rounded-xl border border-dashed">
        <EmptyState
          title="No link pages yet"
          description="Pick short links for an event, a resource list, or a bio, and publish them on one page."
        >
          <template #icon><LayoutList class="h-5 w-5" /></template>
          <template v-if="canEdit" #action>
            <Button size="sm" type="button" @click="createOpen = true"> <Plus class="h-4 w-4" /> New link page </Button>
          </template>
        </EmptyState>
      </div>

      <ul v-else class="mt-6 divide-y rounded-xl border bg-surface">
        <li v-for="linkPage in linkPages" :key="linkPage.id">
          <Link
            :href="route('link-pages.show', linkPage.id)"
            class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-elevated/50 focus-visible:bg-elevated/50 focus-visible:outline-none"
          >
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-foreground">{{ linkPage.name }}</span>
              <span class="block truncate text-xs text-faint">{{ displayUrl(linkPage.public_url) }}</span>
            </span>
            <span class="hidden text-xs tabular-nums text-faint sm:block">{{ linkCount(linkPage.items_count) }}</span>
            <Badge :variant="linkPage.is_published ? 'success' : 'default'" dot>
              {{ linkPage.is_published ? 'Published' : 'Draft' }}
            </Badge>
          </Link>
        </li>
      </ul>
    </div>

    <CreateLinkPageDialog v-if="canEdit" v-model:open="createOpen" :path-prefix="pathPrefix" />
  </AuthenticatedLayout>
</template>
