<script setup lang="ts">
import {
  Archive,
  Copy,
  ExternalLink,
  Folder as FolderIcon,
  FolderInput,
  Inbox,
  Lock,
  MoreHorizontal,
  PencilLine,
  QrCode,
  Timer,
  Trash2,
} from '@lucide/vue';
import { DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger, DropdownMenuPortal } from 'radix-vue';
import { computed } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { relativeTime } from '@/lib/datetime';
import { draggedLink } from '@/lib/dragLink';
import { archiveLink, createQrCodeFor, deleteLink, moveLinkToFolder } from '@/lib/linkActions';
import { displayUrl } from '@/lib/links';
import { copyToClipboard } from '@/lib/toast';

import type { Folder, ShortLink } from './types';

const props = defineProps<{
  link: ShortLink;
  selected: boolean;
  folders: Folder[];
  canEdit: boolean;
  showFolder: boolean;
  countdown?: string | null;
  compact?: boolean;
}>();

const emit = defineEmits<{ select: []; removed: [] }>();

const statusDot = computed(
  () =>
    ({
      active: 'bg-success',
      scheduled: 'bg-accent',
      expired: 'bg-warning',
      disabled: 'bg-danger',
      archived: 'bg-faint',
    })[props.link.status] ?? 'bg-faint',
);

const formatter = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

function openShortUrl() {
  window.open(props.link.short_url, '_blank', 'noopener');
}

function onDragStart(event: DragEvent) {
  draggedLink.value = { id: props.link.id, folderId: props.link.folder?.id ?? null };
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
      <span v-else-if="link.status !== 'active'" class="inline-flex items-center gap-1.5 text-xs capitalize text-muted">
        <span class="h-1.5 w-1.5 rounded-full" :class="statusDot" />{{ link.status }}
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
      <Menu width="w-56">
        <template #trigger>
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-md text-faint opacity-0 transition-[opacity,color,background-color] hover:bg-border-strong/50 hover:text-foreground focus-visible:opacity-100 group-hover/row:opacity-100 data-[state=open]:bg-border-strong/50 data-[state=open]:opacity-100"
            :class="selected ? 'opacity-100' : ''"
            aria-label="More actions"
          >
            <MoreHorizontal class="h-3.5 w-3.5" />
          </button>
        </template>
        <MenuItem :icon="PencilLine" @select="emit('select')">{{ canEdit ? 'Edit details' : 'View details' }}</MenuItem>
        <MenuItem :icon="Copy" @select="copyToClipboard(link.short_url)">Copy short link</MenuItem>
        <MenuItem :icon="ExternalLink" @select="openShortUrl"> Open in new tab </MenuItem>
        <template v-if="canEdit">
          <MenuItem :icon="QrCode" @select="createQrCodeFor(link)">Create QR code</MenuItem>
          <MenuSeparator />
          <DropdownMenuSub v-if="folders.length">
            <DropdownMenuSubTrigger
              class="flex h-8 cursor-default select-none items-center gap-2.5 rounded-lg px-2.5 text-[13px] text-foreground outline-none data-[highlighted]:bg-elevated data-[state=open]:bg-elevated"
            >
              <FolderInput class="h-4 w-4 text-muted" />
              <span class="flex-1">Move to</span>
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
              <DropdownMenuSubContent
                :side-offset="6"
                class="z-[91] max-h-72 w-52 overflow-y-auto rounded-xl bg-overlay p-1 shadow-popover"
              >
                <MenuItem
                  v-for="folder in folders"
                  :key="folder.id"
                  :icon="FolderIcon"
                  :disabled="link.folder?.id === folder.id"
                  @select="moveLinkToFolder(link, folder.id, folder.name)"
                  >{{ folder.name }}</MenuItem
                >
                <MenuSeparator />
                <MenuItem :icon="Inbox" :disabled="!link.folder" @select="moveLinkToFolder(link, null)"
                  >Unfiled</MenuItem
                >
              </DropdownMenuSubContent>
            </DropdownMenuPortal>
          </DropdownMenuSub>
          <MenuItem v-if="link.status !== 'archived'" :icon="Archive" @select="archiveLink(link, () => emit('removed'))"
            >Archive</MenuItem
          >
          <MenuItem :icon="Trash2" destructive @select="deleteLink(link, () => emit('removed'))">Delete</MenuItem>
        </template>
      </Menu>
    </div>
  </div>
</template>
