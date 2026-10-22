<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Copy, ExternalLink, Trash2 } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import { confirmAction } from '@/lib/confirm';
import { useShell } from '@/lib/shell';
import { copyToClipboard, toast } from '@/lib/toast';

import type { LinkPageSummary } from './types';

const props = defineProps<{
  linkPage: LinkPageSummary;
  dirty: boolean;
}>();

const { canEdit } = useShell();

function setPublished(published: boolean) {
  router.post(
    route(published ? 'link-pages.publish' : 'link-pages.unpublish', props.linkPage.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: published ? 'Link page published' : 'Link page unpublished', tone: 'success' }),
    },
  );
}

async function destroy() {
  const confirmed = await confirmAction({
    title: `Delete “${props.linkPage.name}”?`,
    message: 'Its public URL will stop working. The short links on it are not changed.',
    confirmLabel: 'Delete link page',
    destructive: true,
  });
  if (confirmed) router.delete(route('link-pages.destroy', props.linkPage.id));
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <Button variant="ghost" size="sm" type="button" @click="copyToClipboard(linkPage.public_url, 'Page URL copied')">
      <Copy /> Copy URL
    </Button>
    <a
      v-if="linkPage.is_published"
      :href="linkPage.public_url"
      target="_blank"
      rel="noopener"
      class="inline-flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-medium text-muted transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 [&_svg]:h-3.5 [&_svg]:w-3.5"
    >
      <ExternalLink /> Open
    </a>
    <template v-if="canEdit">
      <Button variant="ghost" size="sm" type="button" title="Delete link page" @click="destroy">
        <Trash2 /> Delete
      </Button>
      <Button
        v-if="linkPage.is_published"
        variant="secondary"
        size="sm"
        type="button"
        :disabled="dirty"
        @click="setPublished(false)"
      >
        Unpublish
      </Button>
      <Button v-else size="sm" type="button" :disabled="dirty" @click="setPublished(true)">Publish</Button>
    </template>
  </div>
</template>
