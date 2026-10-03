<script setup lang="ts">
import { Copy, Folder as FolderIcon, Lock, QrCode, Timer } from '@lucide/vue';

import Favicon from '@/Components/Links/Favicon.vue';
import { relativeTime } from '@/lib/datetime';
import { draggedLink } from '@/lib/dragLink';
import { displayUrl } from '@/lib/links';
import { useShell } from '@/lib/shell';
import { copyToClipboard } from '@/lib/toast';
import type { Folder } from '@/types/payloads';
import type { ShortLink } from '@/types/shortLinks';

import { linkStatusDot, linkStatusLabel } from '../linkStatus';
import LinkRowMenu from './LinkRowMenu.vue';

const props = defineProps<{
  link: ShortLink;
  selected: boolean;
  folders: Folder[];
  showFolder: boolean;
  countdown?: string | null;
  compact?: boolean;
}>();

const emit = defineEmits<{ select: []; removed: [] }>();

const { canEdit } = useShell();

const formatter = new Intl.NumberFormat('en-US', {
  notation: 'compact',
  maximumFractionDigits: 1,
});

function onDragStart(event: DragEvent) {
  draggedLink.value = {
    id: props.link.id,
    folderId: props.link.folder?.id ?? null,
  };
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/uri-list', props.link.short_url);
    event.dataTransfer.setData('text/plain', props.link.short_url);
  }
}
</script>

<template>
  <div
    role="option"
    :aria-selected="selected"
    tabindex="-1"
    :data-link-id="link.id"
    class="group/row relative flex cursor-default items-center gap-3 rounded-lg px-3 py-2 outline-none transition-colors duration-100"
    :class="[selected ? 'bg-elevated' : 'hover:bg-elevated/50', draggedLink?.id === link.id ? 'opacity-40' : '']"
    :draggable="canEdit"
    @click="emit('select')"
    @dragstart="onDragStart"
    @dragend="draggedLink = null"
  >
    <Favicon :url="link.destination_url" />

    <div class="min-w-0 flex-1">
      <div class="flex min-w-0 items-center gap-1.5">
        <button
          type="button"
          class="group/copy -mx-1 inline-flex min-w-0 items-center gap-1.5 rounded-md px-1 text-left text-[13px] font-medium text-foreground transition-colors hover:bg-border-strong/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
          title="Copy short link"
          @click.stop="copyToClipboard(link.short_url)"
        >
          <span class="truncate">{{ displayUrl(link.short_url) }}</span>
          <Copy
            class="h-3 w-3 shrink-0 text-faint opacity-0 transition-opacity group-hover/copy:opacity-100 group-focus-visible/copy:opacity-100"
          />
        </button>
        <Lock v-if="link.has_password" class="h-3 w-3 shrink-0 text-faint" aria-label="Password protected" />
        <QrCode
          v-if="link.qr_code_count > 0"
          class="h-3 w-3 shrink-0 text-faint"
          :aria-label="`${link.qr_code_count} QR codes`"
        />
      </div>
      <p class="flex min-w-0 items-center gap-1.5 text-xs text-faint">
        <span class="truncate">{{ displayUrl(link.destination_url) }}</span>
        <template v-if="showFolder && link.folder">
          <span aria-hidden="true">·</span>
          <span class="inline-flex shrink-0 items-center gap-1"
            ><FolderIcon class="h-3 w-3" />{{ link.folder.name }}</span
          >
        </template>
        <template v-for="tag in link.tags.slice(0, 2)" :key="tag.id">
          <span class="hidden shrink-0 rounded bg-elevated px-1 text-[11px] leading-4 text-muted md:inline"
            >#{{ tag.name }}</span
          >
        </template>
      </p>
    </div>

    <div class="hidden shrink-0 items-center gap-5 sm:flex">
      <span
        v-if="countdown"
        class="inline-flex items-center gap-1 text-xs tabular-nums text-accent"
        :title="`Activates ${link.activates_at}`"
      >
        <Timer class="h-3 w-3" />{{ countdown }}
      </span>
      <span v-else-if="link.status !== 'active'" class="inline-flex items-center gap-1.5 text-xs text-muted">
        <span class="h-1.5 w-1.5 rounded-full" :class="linkStatusDot(link.status)" />{{ linkStatusLabel(link.status) }}
      </span>
      <span
        class="w-16 text-right text-xs tabular-nums text-muted"
        :title="`${link.visits} visits · ${link.scans} scans`"
      >
        <span class="font-medium text-foreground">{{ formatter.format(link.visits + link.scans) }}</span>
        <span class="text-faint"> clicks</span>
      </span>
      <span v-if="!compact" class="hidden whitespace-nowrap text-right text-xs text-faint lg:block">{{
        relativeTime(link.created_at)
      }}</span>
    </div>

    <div class="flex shrink-0 items-center" @click.stop>
      <LinkRowMenu
        :link="link"
        :folders="folders"
        :selected="selected"
        @select="emit('select')"
        @removed="emit('removed')"
      />
    </div>
  </div>
</template>
