<script setup lang="ts">
import { CircleAlert, Info, ScanLine, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';

import { DECODE_LABELS, isFixable, printRestrictions } from '../qrReadability';
import type { ExportFormat } from '../types';
import type { ReadabilityState } from './useReadabilityCheck';

const props = defineProps<{ state: ReadabilityState; exportSize: number; canEdit: boolean }>();

defineEmits<{ check: []; applyFixes: [] }>();

const FORMATS: ExportFormat[] = ['png', 'svg'];

const result = computed(() => (props.state.status === 'ready' ? props.state.result : null));
const restrictions = computed(() => (result.value ? printRestrictions(result.value.summary) : []));
const canFix = computed(() => props.canEdit && result.value !== null && isFixable(result.value.warnings));
const hasRun = computed(() => ['ready', 'stale', 'failed'].includes(props.state.status));
</script>

<template>
  <section class="mt-6 rounded-xl border bg-surface" aria-labelledby="readability-heading">
    <header class="flex items-center gap-3 px-4 pb-3 pt-4">
      <div class="min-w-0 flex-1">
        <h2 id="readability-heading" class="text-sm font-medium text-foreground">Readability check</h2>
        <p class="text-xs text-faint">Decodes the PNG and SVG exports at {{ exportSize }} px.</p>
      </div>
      <Button
        variant="secondary"
        size="sm"
        type="button"
        :disabled="state.status === 'blocked'"
        :loading="state.status === 'checking'"
        @click="$emit('check')"
      >
        <ScanLine v-if="state.status !== 'checking'" />
        {{ hasRun ? 'Check again' : 'Check readability' }}
      </Button>
    </header>

    <div class="border-t px-4 py-3 text-[13px]" aria-live="polite">
      <p v-if="state.status === 'blocked'" class="text-muted">{{ state.reason }}</p>
      <p v-else-if="state.status === 'idle'" class="text-muted">
        Run a check before exporting to catch low contrast, missing margins, small modules, and logos that hide too much
        of the code.
      </p>
      <p v-else-if="state.status === 'checking'" class="text-muted">Rendering and decoding the exports…</p>
      <p v-else-if="state.status === 'failed'" class="text-danger">{{ state.message }}</p>
      <p v-else-if="state.status === 'stale'" class="flex items-start gap-2 text-warning">
        <CircleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />
        Settings changed since the last check. Check again before exporting.
      </p>

      <div v-else-if="result" class="grid gap-4">
        <ul class="grid gap-1.5">
          <li v-for="format in FORMATS" :key="format" class="flex items-center gap-3">
            <span class="w-10 text-xs font-medium uppercase text-faint">{{ format }}</span>
            <Badge :variant="DECODE_LABELS[result.formats[format].status].tone" dot>
              {{ DECODE_LABELS[result.formats[format].status].label }}
            </Badge>
          </li>
        </ul>
        <template v-for="format in FORMATS" :key="`${format}-detail`">
          <p v-if="result.formats[format].status === 'mismatch'" class="break-words text-xs text-danger">
            {{ format.toUpperCase() }} decodes to “{{ result.formats[format].decoded }}”.
          </p>
          <p v-else-if="result.formats[format].reason" class="text-xs text-faint">
            {{ format.toUpperCase() }}: {{ result.formats[format].reason }}
          </p>
        </template>

        <ul v-if="result.warnings.length" class="grid gap-3">
          <li v-for="warning in result.warnings" :key="warning.code" class="flex gap-2">
            <TriangleAlert v-if="warning.severity === 'warning'" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-warning" />
            <Info v-else class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
            <span>
              <span class="block font-medium text-foreground">{{ warning.title }}</span>
              <span class="block text-xs text-muted">{{ warning.message }}</span>
            </span>
          </li>
        </ul>
        <div v-if="canFix" class="flex items-center gap-3">
          <Button size="sm" variant="secondary" type="button" @click="$emit('applyFixes')"
            >Use readable settings</Button
          >
          <span class="text-xs text-faint">Updates the draft only. Save to keep it.</span>
        </div>

        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-xs">
          <dt class="text-faint">Encodes</dt>
          <dd class="whitespace-pre-wrap break-all font-mono text-foreground">{{ result.summary.encoded_content }}</dd>
          <template v-if="result.summary.short_link">
            <dt class="text-faint">Short link</dt>
            <dd class="break-all text-foreground">
              {{ result.summary.short_link.short_url }} · {{ result.summary.short_link.status }}
            </dd>
          </template>
          <dt class="text-faint">Output</dt>
          <dd class="tabular-nums text-foreground">
            {{ result.summary.size }} px · {{ result.summary.modules }} modules · {{ result.summary.module_pixels }} px
            per module
          </dd>
          <dt class="text-faint">Quiet zone</dt>
          <dd class="tabular-nums text-foreground">{{ result.summary.quiet_zone }} modules</dd>
          <dt class="text-faint">Error correction</dt>
          <dd class="capitalize text-foreground">{{ result.summary.error_correction }}</dd>
          <dt class="text-faint">Contrast</dt>
          <dd class="tabular-nums text-foreground">
            {{ result.summary.contrast === null ? 'Depends on the printing surface' : `${result.summary.contrast}:1` }}
          </dd>
        </dl>

        <ul v-if="restrictions.length" class="grid gap-1 text-xs text-muted">
          <li v-for="restriction in restrictions" :key="restriction">{{ restriction }}</li>
        </ul>
      </div>
    </div>

    <p class="border-t px-4 py-3 text-xs text-faint">
      A screen check cannot guarantee real-world scanning. Print a test copy at its final size and material, then scan
      it with a few phones before printing in volume.
    </p>
  </section>
</template>
