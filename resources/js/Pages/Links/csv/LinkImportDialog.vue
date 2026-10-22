<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FileUp, Info } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Select from '@/Components/ui/Select.vue';
import type { Domain } from '@/types/payloads';

import {
  type ImportPreview,
  type ImportResult,
  type ImportRow,
  LINK_CSV_COLUMNS,
  mappingOptions,
  mappingPayload,
  mappingSelection,
  previewHeadline,
  resultHeadline,
} from './linkCsv';

const props = defineProps<{ domains: Domain[]; preferredDomainId: number | null }>();

const open = defineModel<boolean>('open', { default: false });

const file = ref<File | null>(null);
const domainId = ref<number | ''>('');
const selection = ref<Record<string, string>>({});
const preview = ref<ImportPreview | null>(null);
const result = ref<ImportResult | null>(null);
const errors = ref<Record<string, string>>({});
const processing = ref(false);
const input = ref<HTMLInputElement | null>(null);
const previewedWith = ref('');

const currentChoice = computed(() => JSON.stringify([selection.value, domainId.value]));
const stale = computed(() => preview.value !== null && previewedWith.value !== currentChoice.value);

const domainOptions = computed(() => props.domains.map((domain) => ({ value: domain.id, label: domain.hostname })));
const columnOptions = computed(() => mappingOptions(preview.value?.headers ?? []));
const rows = computed<ImportRow[]>(() => result.value?.rows ?? preview.value?.rows ?? []);
const error = computed(() => errors.value.file ?? errors.value.mapping ?? errors.value.domain_id);

watch(open, (value) => {
  if (!value) return;
  file.value = null;
  preview.value = null;
  result.value = null;
  errors.value = {};
  selection.value = {};
  domainId.value =
    props.domains.find((domain) => domain.id === props.preferredDomainId)?.id ?? props.domains[0]?.id ?? '';
});

function choose(event: Event) {
  const chosen = (event.target as HTMLInputElement).files?.[0] ?? null;
  if (!chosen) return;
  file.value = chosen;
  preview.value = null;
  result.value = null;
  selection.value = {};
  submit('short-links.import.preview');
}

function submit(name: 'short-links.import.preview' | 'short-links.import') {
  if (!file.value || processing.value) return;

  const mapping = preview.value ? mappingPayload(selection.value) : undefined;
  router.post(
    route(name),
    { file: file.value, domain_id: domainId.value || null, ...(mapping ? { mapping } : {}) },
    {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      only: name === 'short-links.import' ? ['links', 'linksPagination', 'folders', 'tags', 'navigation'] : ['filters'],
      onStart: () => {
        processing.value = true;
        errors.value = {};
      },
      onFinish: () => {
        processing.value = false;
      },
      onError: (bag) => {
        errors.value = bag;
      },
      onSuccess: (page) => {
        const flash = page.flash as { linkImportPreview?: ImportPreview; linkImportResult?: ImportResult };
        if (flash.linkImportResult) {
          result.value = flash.linkImportResult;
          return;
        }
        if (flash.linkImportPreview) {
          preview.value = flash.linkImportPreview;
          selection.value = mappingSelection(flash.linkImportPreview.mapping);
          previewedWith.value = currentChoice.value;
        }
      },
    },
  );
}

function badge(row: ImportRow): { variant: 'success' | 'danger' | 'default'; label: string } {
  if (row.status === 'ready') return { variant: 'default', label: 'Ready' };
  if (row.status === 'created') return { variant: 'success', label: 'Created' };
  return { variant: 'danger', label: 'Skipped' };
}
</script>

<template>
  <Dialog
    v-model:open="open"
    size="xl"
    title="Import links from CSV"
    description="Check the preview before anything is created. Imports only add new links and never change existing ones."
  >
    <div class="space-y-4 px-5 pb-5 pt-4">
      <div v-if="!result" class="flex flex-wrap items-end gap-3">
        <div class="grid gap-1.5">
          <span class="text-[13px] font-medium text-foreground">File</span>
          <div class="flex items-center gap-2">
            <Button variant="secondary" type="button" :loading="processing && !preview" @click="input?.click()">
              <FileUp /> {{ file ? 'Choose another file' : 'Choose CSV file' }}
            </Button>
            <span v-if="file" class="max-w-56 truncate text-[13px] text-muted">{{ file.name }}</span>
          </div>
          <input ref="input" type="file" accept=".csv,.tsv,.txt,text/csv" class="sr-only" @change="choose" />
        </div>
        <Field v-if="domainOptions.length" label="Domain for rows without one" class="min-w-56">
          <Select v-model="domainId" :options="domainOptions" aria-label="Domain for rows without one" />
        </Field>
      </div>

      <p v-if="error" class="text-[13px] text-danger" role="alert">{{ error }}</p>

      <div
        v-if="!preview && !result"
        class="flex gap-2.5 rounded-xl bg-elevated/60 p-3 text-[13px] leading-relaxed text-muted"
      >
        <Info class="mt-0.5 h-4 w-4 shrink-0 text-faint" />
        <div class="space-y-1.5">
          <p>
            Use UTF-8, one link per row and a header row. Up to 1,000 links and 1 MB per file. Start from the
            <a :href="route('links.import.example')" class="text-accent underline-offset-2 hover:underline"
              >example file</a
            >
            or an Openlink export.
          </p>
          <p>
            A short URL is kept only on a domain this workspace controls. Links from a shared domain such as bit.ly
            cannot keep their old public URL by importing a CSV: leave the domain column unmapped and they get a short
            URL on the domain chosen above.
          </p>
          <p>Visitor passwords, routing rules, QR codes and analytics are not imported.</p>
        </div>
      </div>

      <section v-if="preview && !result" class="space-y-3">
        <h3 class="text-[13px] font-medium text-foreground">Columns</h3>
        <div class="grid gap-2 sm:grid-cols-3">
          <Field v-for="column in LINK_CSV_COLUMNS" :key="column.key" :label="column.label">
            <Select v-model="selection[column.key]" :options="columnOptions" size="sm" :aria-label="column.label" />
          </Field>
        </div>
        <div class="flex items-center justify-end gap-3">
          <span v-if="stale" class="text-xs text-muted">Update the preview to import with these columns.</span>
          <Button
            :variant="stale ? 'primary' : 'ghost'"
            size="sm"
            type="button"
            :loading="processing"
            @click="submit('short-links.import.preview')"
          >
            Update preview
          </Button>
        </div>
      </section>

      <section v-if="rows.length" class="space-y-2" aria-live="polite">
        <p class="text-[13px] font-medium text-foreground">
          {{ result ? resultHeadline(result.summary) : preview ? previewHeadline(preview.summary) : '' }}
        </p>
        <p
          v-if="preview && !result && (preview.summary.new_folders.length || preview.summary.new_tags.length)"
          class="text-xs text-muted"
        >
          <template v-if="preview.summary.new_folders.length"
            >New folders: {{ preview.summary.new_folders.join(', ') }}.
          </template>
          <template v-if="preview.summary.new_tags.length"
            >New tags: {{ preview.summary.new_tags.join(', ') }}.</template
          >
        </p>
        <ol class="max-h-72 divide-y overflow-y-auto rounded-xl border text-[13px]">
          <li v-for="row in rows" :key="row.row" class="flex gap-3 px-3 py-2">
            <span class="w-10 shrink-0 tabular-nums text-faint">{{ row.row }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-foreground">
                {{ row.short_url ?? (row.domain ? `${row.domain}/…` : 'Generated slug') }}
              </p>
              <p class="truncate text-xs text-faint">{{ row.destination_url || 'No destination URL' }}</p>
              <p v-for="message in row.errors" :key="message" class="text-xs text-danger">{{ message }}</p>
            </div>
            <Badge :variant="badge(row).variant" class="self-start">{{ badge(row).label }}</Badge>
          </li>
        </ol>
      </section>

      <div class="flex justify-end gap-2">
        <Button variant="secondary" type="button" @click="open = false">{{ result ? 'Done' : 'Cancel' }}</Button>
        <Button
          v-if="preview && !result"
          type="button"
          :loading="processing"
          :disabled="stale || preview.summary.ready === 0"
          @click="submit('short-links.import')"
        >
          Import {{ preview.summary.ready.toLocaleString() }} link{{ preview.summary.ready === 1 ? '' : 's' }}
        </Button>
      </div>
    </div>
  </Dialog>
</template>
