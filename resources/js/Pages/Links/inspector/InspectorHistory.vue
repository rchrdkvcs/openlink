<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { confirmAction } from '@/lib/confirm';
import { relativeTime, shortDate } from '@/lib/datetime';
import { fetchJson } from '@/lib/http';
import { useShell } from '@/lib/shell';
import { actionLabel, actorLabel, channelLabel, describeChange, revisionSummary } from '@/lib/shortLinks/history';
import { toast } from '@/lib/toast';
import type { LinkRevision, LinkRevisionDetail, LinkRevisionPage, ShortLink } from '@/types/shortLinks';

const props = defineProps<{ link: ShortLink; hasUnsavedChanges: boolean }>();

const emit = defineEmits<{ back: [] }>();

const { canEdit } = useShell();

const heading = ref<HTMLElement | null>(null);
let requestVersion = 0;
let previewVersion = 0;
const revisions = ref<LinkRevision[]>([]);
const page = ref(0);
const lastPage = ref(1);
const loading = ref(false);
const failed = ref(false);
const preview = ref<LinkRevisionDetail | null>(null);
const previewing = ref<number | null>(null);
const restoring = ref(false);

async function load(nextPage: number) {
  if (loading.value) return;
  const version = requestVersion;
  loading.value = true;
  failed.value = false;
  try {
    const result = await fetchJson<LinkRevisionPage>(
      `${route('short-links.revisions.index', props.link.id)}?page=${nextPage}`,
    );
    if (version !== requestVersion) return;
    revisions.value = nextPage === 1 ? result.data : [...revisions.value, ...result.data];
    page.value = result.meta.current_page;
    lastPage.value = result.meta.last_page;
  } catch {
    if (version === requestVersion) failed.value = true;
  } finally {
    if (version === requestVersion) loading.value = false;
  }
}

function reset() {
  requestVersion += 1;
  previewVersion += 1;
  loading.value = false;
  revisions.value = [];
  page.value = 0;
  preview.value = null;
  previewing.value = null;
  load(1);
}

onMounted(() => {
  heading.value?.focus();
  load(1);
});
onUnmounted(() => {
  requestVersion += 1;
  previewVersion += 1;
});
watch(() => [props.link.id, props.link.revision], reset);

async function showPreview(number: number) {
  const version = ++previewVersion;
  if (preview.value?.number === number) {
    preview.value = null;
    previewing.value = null;
    return;
  }
  previewing.value = number;
  try {
    const result = await fetchJson<{ data: LinkRevisionDetail }>(
      route('short-links.revisions.show', [props.link.id, number]),
    );
    if (version === previewVersion) preview.value = result.data;
  } catch {
    if (version !== previewVersion) return;
    toast({
      title: 'Could not load this revision',
      description: 'Check your connection and try again.',
      tone: 'danger',
    });
  } finally {
    if (version === previewVersion) previewing.value = null;
  }
}

async function restore(number: number) {
  if (!canEdit.value || restoring.value) return;
  if (
    props.hasUnsavedChanges &&
    !(await confirmAction({
      title: 'Discard unsaved changes?',
      message: `Restoring revision ${number} replaces the edits you haven't saved in this link.`,
      confirmLabel: 'Discard and restore',
      destructive: true,
    }))
  ) {
    return;
  }

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
  <section class="flex min-h-0 flex-1 flex-col" aria-label="Link history">
    <div class="border-b px-5 py-4">
      <Button variant="ghost" size="sm" type="button" class="-ms-2.5 mb-3" @click="emit('back')">
        <ArrowLeft class="h-3.5 w-3.5" /> Back to details
      </Button>
      <h2 ref="heading" tabindex="-1" class="text-base font-semibold outline-none">History</h2>
      <p class="mt-1 text-xs leading-relaxed text-faint">
        Newest changes first. Restoring adds a new revision and keeps all earlier changes.
      </p>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5" :aria-busy="loading">
      <p
        v-if="hasUnsavedChanges"
        class="mb-5 rounded-lg border border-warning/30 bg-warning/5 px-3 py-2.5 text-xs text-warning"
      >
        You have unsaved changes. Go back to keep editing, or preview a restore before replacing them.
      </p>
      <p v-if="failed" role="alert" class="mb-4 text-[13px] text-danger">
        History could not be loaded.
        <button type="button" class="underline" @click="load(page + 1)">Try again</button>
      </p>
      <p v-else-if="!loading && page > 0 && !revisions.length" class="text-[13px] text-faint">
        Changes made from now on appear here.
      </p>
      <p v-if="loading && !revisions.length" role="status" class="text-[13px] text-faint">Loading history…</p>

      <ol aria-label="Revisions, newest first" class="space-y-6 border-s border-border ps-4">
        <li v-for="revision in revisions" :key="revision.number" class="relative min-w-0 text-[13px]">
          <span
            class="absolute -start-[21px] top-1.5 h-2 w-2 rounded-full bg-border-strong ring-4 ring-canvas"
            aria-hidden="true"
          />
          <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <span class="font-medium text-foreground">{{ actionLabel(revision) }}</span>
            <span class="text-xs tabular-nums text-faint"
              >Revision {{ revision.number }}{{ revision.number === link.revision ? ' · Current' : '' }}</span
            >
          </div>
          <p class="mt-1 text-xs leading-relaxed text-muted">{{ revisionSummary(revision) }}</p>
          <p class="mt-2 text-xs text-faint">{{ actorLabel(revision) }} · {{ channelLabel(revision.channel) }}</p>
          <time :datetime="revision.created_at" class="mt-1 block text-xs leading-relaxed text-faint">
            {{ shortDate(revision.created_at) }} · {{ relativeTime(revision.created_at) }}
          </time>

          <dl v-if="revision.changes.length" class="mt-3 space-y-3 rounded-lg border bg-surface px-3 py-3 text-xs">
            <div
              v-for="change in revision.changes.map(describeChange)"
              :key="change.label"
              class="grid gap-1 [overflow-wrap:anywhere]"
            >
              <dt class="font-medium text-foreground">{{ change.label }}</dt>
              <dd class="min-w-0 space-y-1 leading-relaxed text-muted">
                <p v-if="revision.action !== 'created' && revision.action !== 'baseline'">
                  <span class="text-faint">Before: </span>{{ change.before }}
                </p>
                <p>
                  <span class="text-faint">{{
                    revision.action === 'created' || revision.action === 'baseline' ? 'Value: ' : 'After: '
                  }}</span
                  >{{ change.after }}
                </p>
              </dd>
            </div>
          </dl>

          <div v-if="canEdit && revision.number !== link.revision" class="mt-2">
            <Button
              variant="secondary"
              size="sm"
              type="button"
              :loading="previewing === revision.number"
              :disabled="restoring"
              :aria-expanded="preview?.number === revision.number"
              @click="showPreview(revision.number)"
            >
              {{ preview?.number === revision.number ? 'Hide restore preview' : 'Preview restore' }}
            </Button>
          </div>

          <div
            v-if="canEdit && preview?.number === revision.number"
            class="mt-3 space-y-3 rounded-lg border bg-surface px-3 py-3 text-xs"
          >
            <p v-if="!preview.restore.restorable" class="text-muted">{{ preview.restore.reason }}</p>
            <template v-else>
              <p class="font-medium text-foreground">Restoring will change</p>
              <dl class="space-y-3">
                <div
                  v-for="change in preview.restore.changes.map(describeChange)"
                  :key="change.label"
                  class="grid gap-1 [overflow-wrap:anywhere]"
                >
                  <dt class="font-medium text-foreground">{{ change.label }}</dt>
                  <dd class="min-w-0 space-y-1 leading-relaxed text-muted">
                    <p><span class="text-faint">Before: </span>{{ change.before }}</p>
                    <p><span class="text-faint">After: </span>{{ change.after }}</p>
                  </dd>
                </div>
              </dl>
              <ul v-if="preview.restore.notes.length" class="list-disc space-y-1 ps-4 text-warning">
                <li v-for="note in preview.restore.notes" :key="note">{{ note }}</li>
              </ul>
              <p class="leading-relaxed text-faint">
                Visits, analytics, QR codes, the short URL and visitor password stay as they are.
              </p>
              <div class="flex flex-wrap justify-end gap-2">
                <Button variant="ghost" size="sm" type="button" :disabled="restoring" @click="preview = null"
                  >Cancel</Button
                >
                <Button size="sm" type="button" :loading="restoring" @click="restore(revision.number)">
                  Restore revision {{ revision.number }}
                </Button>
              </div>
            </template>
          </div>
        </li>
      </ol>

      <Button
        v-if="page > 0 && page < lastPage && !failed"
        variant="secondary"
        size="sm"
        type="button"
        class="mt-6 w-full"
        :loading="loading"
        @click="load(page + 1)"
      >
        Show older changes
      </Button>
    </div>
  </section>
</template>
