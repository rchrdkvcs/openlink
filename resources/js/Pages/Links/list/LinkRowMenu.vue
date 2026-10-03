<script setup lang="ts">
import {
  Archive,
  Copy,
  ExternalLink,
  Folder as FolderIcon,
  FolderInput,
  Inbox,
  MoreHorizontal,
  PencilLine,
  QrCode,
  Trash2,
} from '@lucide/vue';
import { DropdownMenuPortal, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger } from 'radix-vue';

import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { archiveLink, createQrCodeFor, deleteLink, moveLinkToFolder } from '@/lib/linkActions';
import { useShell } from '@/lib/shell';
import { copyToClipboard } from '@/lib/toast';
import type { Folder } from '@/types/payloads';
import type { ShortLink } from '@/types/shortLinks';

const props = defineProps<{
  link: ShortLink;
  folders: Folder[];
  selected: boolean;
}>();

const emit = defineEmits<{ select: []; removed: [] }>();

const { canEdit } = useShell();

function openShortUrl() {
  window.open(props.link.short_url, '_blank', 'noopener');
}
</script>

<template>
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
            <MenuItem :icon="Inbox" :disabled="!link.folder" @select="moveLinkToFolder(link, null)">Unfiled</MenuItem>
          </DropdownMenuSubContent>
        </DropdownMenuPortal>
      </DropdownMenuSub>
      <MenuItem v-if="link.status !== 'archived'" :icon="Archive" @select="archiveLink(link, () => emit('removed'))"
        >Archive</MenuItem
      >
      <MenuItem :icon="Trash2" destructive @select="deleteLink(link, () => emit('removed'))">Delete</MenuItem>
    </template>
  </Menu>
</template>
