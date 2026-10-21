<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { relativeTime, shortDate } from '@/lib/datetime';
import { fetchJson } from '@/lib/http';
import { useShell } from '@/lib/shell';
import { actionLabel, actorLabel, channelLabel, describeChange } from '@/lib/shortLinks/history';
import { toast } from '@/lib/toast';
import type { LinkRevision, LinkRevisionDetail, LinkRevisionPage, ShortLink } from '@/types/shortLinks';

import InspectorRow from './InspectorRow.vue';

const props = defineProps<{ link: ShortLink }>();

const { canEdit } = useShell();

const open = ref(false);
const revisions = ref<LinkRevision[]>([]);
const page = ref(0);
const lastPage = ref(1);
const loading = ref(false);
const failed = ref(false);
const preview = ref<LinkRevisionDetail | null>(null);
const previewing = ref<number | null>(null);
const restoring = ref(false);

async function load(nextPage: number) {
  loading.value = true;
  failed.value = false;
  try {
    const result = await fetchJson<LinkRevisionPage>(
      `${route('short-links.revisions.index', props.link.id)}?page=${nextPage}`,
    );
    revisions.value = nextPage === 1 ? result.data : [...revisions.value, ...result.data];
    page.value = result.meta.current_page;
    lastPage.value = result.meta.last_page;
  } catch {
    failed.value = true;
  } finally {
    loading.value = false;
  }
}

function reset() {
  revisions.value = [];
  page.value = 0;
  preview.value = null;
  previewing.value = null;
  if (open.value) load(1);
}

watch(open, (isOpen) => {
  if (isOpen && page.value === 0) load(1);
});
watch(() => [props.link.id, props.link.revision], reset);

async function showPreview(number: number) {
  if (preview.value?.number === number) {
    preview.value = null;
    return;
  }
  previewing.value = number;
  try {
    const result = await fetchJson<{ data: LinkRevisionDetail }>(
      route('short-links.revisions.show', [props.link.id, number]),
    );
    preview.value = result.data;
  } catch {
    toast({
      title: 'Could not load this revision',
      description: 'Check your connection and try again.',
      tone: 'danger',
    });
  } finally {
    previewing.value = null;
  }
}

function restore(number: number) {
  restoring.value = true;
  router.post(
    route('short-links.revisions.restore', [props.link.id, number]),
    { expected_revision: props.link.revision },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast({ title: `Revision ${number} restored`, tone: 'success', duration: 2500 }),
      onError: (errors) =>
        toast({ title: 'Could not restore this revision', description: Object.values(errors)[0], tone: 'danger' }),
      onFinish: () => (restoring.value = false),
    },
  );
}
</script>

<template>
  <div class="mx-5 mb-4 overflow-hidden rounded-xl border bg-surface">
    <InspectorRow
      v-model:open="open"
      :icon="History"
      label="History"
      :summary="link.revision ? `Revision ${link.revision}` : 'No changes recorded'"
    >
      <p v-if="failed" class="text-xs text-danger">
        History could not be loaded.
        <button type="button" class="underline" @click="load(page + 1)">Try again</button>
      </p>
      <p v-else-if="!loading && page > 0 && !revisions.length" class="text-xs text-faint">
        Changes made from now on appear here.
      </p>

      <ol class="space-y-3">
        <li v-for="revision in revisions" :key="revision.number" class="text-[13px]">
          <div class="flex items-baseline gap-2">
            <span class="font-medium text-foreground">{{ actionLabel(revision) }}</span>
            <span class="min-w-0 flex-1 text-faint">
              {{ actorLabel(revision) }} · {{ channelLabel(revision.channel) }} ·
              <time :datetime="revision.created_at" :title="shortDate(revision.created_at)">{{
                relativeTime(revision.created_at)
              }}</time>
            </span>
            <span class="shrink-0 text-xs tabular-nums text-faint">#{{ revision.number }}</span>
          </div>

          <dl class="mt-1 space-y-0.5 text-xs">
            <div v-for="change in revision.changes.map(describeChange)" :key="change.label" class="flex gap-1.5">
              <dt class="shrink-0 text-faint">{{ change.label }}</dt>
              <dd class="min-w-0 text-muted [overflow-wrap:anywhere]">
                <template v-if="revision.action !== 'created'">{{ change.before }} → </template>{{ change.after }}
              </dd>
            </div>
          </dl>

          <div v-if="canEdit && revision.number !== link.revision" class="mt-1.5">
            <Button
              variant="ghost"
              size="sm"
              type="button"
              class="-ml-2.5"
              :loading="previewing === revision.number"
              :aria-expanded="preview?.number === revision.number"
              @click="showPreview(revision.number)"
            >
              {{ preview?.number === revision.number ? 'Hide restore preview' : 'Preview restore' }}
            </Button>
          </div>

          <div
            v-if="preview?.number === revision.number"
            class="mt-1.5 space-y-2 rounded-lg border bg-canvas px-3 py-2.5 text-xs"
          >
            <p v-if="!preview.restore.restorable" class="text-muted">{{ preview.restore.reason }}</p>
            <template v-else>
              <p class="font-medium text-foreground">Restoring will change</p>
              <dl class="space-y-0.5">
                <div
                  v-for="change in preview.restore.changes.map(describeChange)"
                  :key="change.label"
                  class="flex gap-1.5"
                >
                  <dt class="shrink-0 text-faint">{{ change.label }}</dt>
                  <dd class="min-w-0 text-muted [overflow-wrap:anywhere]">{{ change.before }} → {{ change.after }}</dd>
                </div>
              </dl>
              <ul v-if="preview.restore.notes.length" class="list-disc space-y-0.5 pl-4 text-warning">
                <li v-for="note in preview.restore.notes" :key="note">{{ note }}</li>
              </ul>
              <p class="text-faint">Visits, analytics, QR codes and the short URL stay as they are.</p>
              <div class="flex justify-end gap-1.5">
                <Button variant="ghost" size="sm" type="button" @click="preview = null">Cancel</Button>
                <Button size="sm" type="button" :loading="restoring" @click="restore(revision.number)">
                  Restore revision {{ revision.number }}
                </Button>
              </div>
            </template>
          </div>
        </li>
      </ol>

      <Button
        v-if="page < lastPage || loading"
        variant="secondary"
        size="sm"
        type="button"
        class="w-full"
        :loading="loading"
        @click="load(page + 1)"
      >
        Show older changes
      </Button>
    </InspectorRow>
  </div>
</template>
