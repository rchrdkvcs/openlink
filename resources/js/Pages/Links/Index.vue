<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
  Archive,
  ExternalLink,
  Folder as FolderIcon,
  Globe,
  Link2,
  Lock,
  Pencil,
  Plus,
  QrCode,
  Search,
  Settings2,
  Timer,
  Trash2,
} from '@lucide/vue';
import { ref } from 'vue';

import Modal from '@/Components/Modal.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Popover from '@/Components/ui/Popover.vue';
import Select from '@/Components/ui/Select.vue';
import SelectOption from '@/Components/ui/SelectOption.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { originOf } from '@/lib/links';

import CreateLinkDrawer from './CreateLinkDrawer.vue';
import EditLinkDrawer from './EditLinkDrawer.vue';
import FolderNavigation from './FolderNavigation.vue';
import type { Folder, LinksPageProps, ShortLink } from './types';
import { useActivationCountdown } from './useActivationCountdown';
import { useLinkForms } from './useLinkForms';
import { useLinkGroups } from './useLinkGroups';

const props = defineProps<LinksPageProps>();

const filters = ref({ search: '', status: '', tag: '' });
const createOpen = ref(false);
const selectedLink = ref<ShortLink | null>(null);
const {
  groups,
  totalMatching,
  hasActiveFilters,
  dragLinkId,
  dropGroupKey,
  selectedFolderKey,
  selectedGroup,
  visibleLinks,
  moveLink,
  onDrop,
} = useLinkGroups(props, filters);
const failedFavicons = ref<Set<string>>(new Set());

const {
  confirmation,
  requestConfirmation,
  copiedLinkId,
  usableDomains,
  linkForm,
  editForm,
  submitLink,
  updateLink,
  archiveLink,
  deleteLink,
  copyShortUrl,
  statusVariant,
} = useLinkForms(props, selectedLink, createOpen);

const { countdownFor, activationTitle } = useActivationCountdown(props);

// ── Folder CRUD ──────────────────────────────────────────────────────────────

const folderForm = useForm({ name: '' });
const creatingFolder = ref(false);
const renamingFolderId = ref<number | null>(null);

function startCreateFolder() {
  renamingFolderId.value = null;
  folderForm.reset();
  folderForm.clearErrors();
  creatingFolder.value = true;
}

function startRenameFolder(folder: Folder) {
  renamingFolderId.value = folder.id;
  folderForm.name = folder.name;
  folderForm.clearErrors();
  creatingFolder.value = true;
}

function submitFolder() {
  if (folderForm.processing) return;
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      creatingFolder.value = false;
      folderForm.reset();
    },
  };
  if (renamingFolderId.value !== null) {
    folderForm.patch(route('folders.update', renamingFolderId.value), options);
  } else {
    folderForm.post(route('folders.store'), options);
  }
}

function openCreateLink() {
  linkForm.defaults({ folder_id: selectedGroup.value?.folder ? String(selectedGroup.value.folder.id) : '' });
  linkForm.reset('folder_id');
  createOpen.value = true;
}

function deleteFolder(folder: Folder, linkCount: number) {
  const detail = linkCount > 0 ? ` Its ${linkCount} link${linkCount > 1 ? 's' : ''} will move to Unfiled.` : '';
  requestConfirmation({
    title: `Delete folder “${folder.name}”?`,
    description: `This removes the folder from this workspace.${detail}`,
    action: () => router.delete(route('folders.destroy', folder.id), { preserveScroll: true }),
  });
}

function parseDisplayUrl(url: string): URL | null {
  try {
    return new URL(url.includes('://') ? url : `https://${url}`);
  } catch {
    return null;
  }
}

function urlWithoutProtocol(url: string) {
  const parsed = parseDisplayUrl(url);

  if (!parsed) {
    return url.replace(/^https?:\/\//, '');
  }

  const path = parsed.pathname === '/' ? '' : parsed.pathname;

  return `${parsed.host}${path}${parsed.search}`;
}

function destinationHost(url: string) {
  return parseDisplayUrl(url)?.host ?? urlWithoutProtocol(url).split('/')[0];
}

function faviconUrl(url: string) {
  const origin = originOf(url);

  return origin ? route('favicons.show', { url: origin }) : null;
}

function hasFavicon(url: string) {
  const favicon = faviconUrl(url);

  return Boolean(favicon && !failedFavicons.value.has(favicon));
}

function markFaviconFailed(url: string) {
  const favicon = faviconUrl(url);

  if (!favicon) {
    return;
  }

  const next = new Set(failedFavicons.value);
  next.add(favicon);
  failedFavicons.value = next;
}
</script>

<template>
  <Head title="Short links" />

  <AuthenticatedLayout>
    <div class="ui-page">
      <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight">Links</h1>
        <p class="mt-1 text-sm text-muted">Your short links, organised into a shared library.</p>
      </div>

      <div class="grid min-w-0 gap-6 xl:grid-cols-[184px_minmax(0,1fr)]">
        <FolderNavigation
          :groups="groups"
          :selected="selectedFolderKey"
          :total="totalMatching"
          :can-manage="canManageWorkspace"
          :can-move="canEditWorkspace"
          :drop-key="dragLinkId !== null ? dropGroupKey : null"
          @select="selectedFolderKey = $event"
          @create="startCreateFolder"
          @rename="$event.folder && startRenameFolder($event.folder)"
          @delete="
            $event.folder &&
            deleteFolder($event.folder, links.filter((link) => link.folder?.id === $event.folder?.id).length)
          "
          @drag-over="dropGroupKey = $event"
          @drop="onDrop"
        />
        <div class="min-w-0">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
              <h2 class="truncate text-sm font-medium">
                {{ selectedFolderKey === 'all' ? 'All links' : (selectedGroup?.folder?.name ?? 'Unfiled') }}
              </h2>
              <span class="text-xs tabular-nums text-faint">{{ visibleLinks.length }}</span>
            </div>
            <div class="flex items-center gap-1">
              <template v-if="selectedGroup?.folder && canManageWorkspace">
                <IconButton title="Rename folder" @click="startRenameFolder(selectedGroup.folder)"
                  ><Pencil class="h-3.5 w-3.5"
                /></IconButton>
                <IconButton
                  title="Delete folder"
                  @click="
                    deleteFolder(
                      selectedGroup.folder,
                      links.filter((link) => link.folder?.id === selectedGroup?.folder?.id).length,
                    )
                  "
                  ><Trash2 class="h-3.5 w-3.5"
                /></IconButton>
              </template>
              <Button v-if="canEditWorkspace" size="sm" type="button" @click="openCreateLink"
                ><Plus class="h-3.5 w-3.5" /> New link</Button
              >
            </div>
          </div>
          <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="relative min-w-0 basis-full sm:min-w-40 sm:flex-1 sm:basis-0">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
              <input v-model="filters.search" aria-label="Search links" class="h-9 pl-9" placeholder="Search links…" />
            </div>
            <Select aria-label="Filter by status" v-model="filters.status" class="h-9 w-36">
              <SelectOption value="">All statuses</SelectOption><SelectOption value="active">Active</SelectOption
              ><SelectOption value="scheduled">Scheduled</SelectOption
              ><SelectOption value="expired">Expired</SelectOption><SelectOption value="disabled">Disabled</SelectOption
              ><SelectOption value="archived">Archived</SelectOption>
            </Select>
            <Select aria-label="Filter by tag" v-model="filters.tag" class="h-9 w-32">
              <SelectOption value="">All tags</SelectOption
              ><SelectOption v-for="tag in tags" :key="tag.id" :value="tag.name">{{ tag.name }}</SelectOption>
            </Select>
          </div>
          <section class="ui-panel p-2" aria-label="Links in selected folder">
            <article
              v-for="link in visibleLinks"
              :key="link.id"
              class="ui-list-row group/r flex flex-wrap items-center gap-x-5 gap-y-3"
              :class="[
                dragLinkId === link.id ? 'opacity-40' : '',
                canEditWorkspace ? 'cursor-grab active:cursor-grabbing' : '',
              ]"
              :draggable="canEditWorkspace"
              @dragstart="dragLinkId = link.id"
              @dragend="
                dragLinkId = null;
                dropGroupKey = null;
              "
            >
              <div class="min-w-0 basis-full xl:min-w-48 xl:flex-1 xl:basis-0">
                <div class="flex min-w-0 items-center gap-1.5">
                  <a
                    :href="link.short_url"
                    target="_blank"
                    class="truncate text-sm font-medium text-foreground hover:text-accent"
                  >
                    {{ urlWithoutProtocol(link.short_url) }}
                  </a>
                  <button
                    class="shrink-0 rounded p-1 text-faint transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover/r:opacity-100 [@media(hover:hover)]:opacity-0"
                    title="Copy short URL"
                    @click="copyShortUrl(link)"
                  >
                    <CopyCheckIcon :copied="copiedLinkId === link.id" />
                  </button>
                  <span
                    v-if="link.qr_code_count > 0"
                    class="grid h-5 w-5 shrink-0 place-items-center rounded text-accent"
                    :title="`${link.qr_code_count} linked QR code${link.qr_code_count === 1 ? '' : 's'}`"
                    :aria-label="`${link.qr_code_count} linked QR code${link.qr_code_count === 1 ? '' : 's'}`"
                  >
                    <QrCode class="h-3.5 w-3.5" aria-hidden="true" />
                  </span>
                </div>

                <a
                  :href="link.destination_url"
                  target="_blank"
                  rel="noopener"
                  class="mt-1 flex min-w-0 items-center gap-2 text-[13px] text-muted transition-colors hover:text-foreground"
                  :title="urlWithoutProtocol(link.destination_url)"
                >
                  <img
                    v-if="hasFavicon(link.destination_url)"
                    :src="faviconUrl(link.destination_url) ?? undefined"
                    alt=""
                    class="h-4 w-4 shrink-0 rounded-[3px] bg-elevated"
                    loading="lazy"
                    @error="markFaviconFailed(link.destination_url)"
                  />
                  <span v-else class="grid h-4 w-4 shrink-0 place-items-center rounded-[3px] bg-elevated">
                    <Globe class="h-3 w-3 text-faint" />
                  </span>
                  <span class="min-w-0 truncate">{{ destinationHost(link.destination_url) }}</span>
                </a>

                <button
                  v-if="selectedFolderKey === 'all' && link.folder"
                  type="button"
                  class="mt-1 inline-flex max-w-full items-center gap-1 text-xs text-faint hover:text-foreground"
                  @click="selectedFolderKey = String(link.folder.id)"
                >
                  <FolderIcon class="h-3 w-3 shrink-0" /><span class="truncate">{{ link.folder.name }}</span>
                </button>
              </div>
              <div class="flex min-w-0 items-center gap-2">
                <Badge :variant="statusVariant(link.status)" dot>{{ link.status }}</Badge>
                <span
                  v-if="countdownFor(link)"
                  class="inline-flex w-20 shrink-0 items-center gap-1 whitespace-nowrap text-xs tabular-nums text-faint"
                  :title="activationTitle(link)"
                >
                  <Timer class="h-3 w-3 shrink-0" />
                  {{ countdownFor(link) }}
                </span>
                <Lock v-if="link.has_password" class="h-3.5 w-3.5 shrink-0 text-warning" />
              </div>

              <div class="text-[13px] tabular-nums text-muted">
                <span class="font-medium text-foreground">{{ link.visits }}</span> visits
                <span class="mx-1 text-faint">·</span>
                <span class="font-medium text-foreground">{{ link.scans }}</span> scans
              </div>

              <div class="flex min-w-0 flex-wrap gap-1">
                <span
                  v-for="tag in link.tags"
                  :key="tag.id"
                  class="rounded bg-elevated px-1.5 py-0.5 text-[11px] text-muted"
                  >#{{ tag.name }}</span
                >
              </div>

              <div class="ms-auto flex items-center gap-0.5">
                <a :href="link.short_url" target="_blank" class="ui-icon-button" title="Open">
                  <ExternalLink class="h-4 w-4" />
                </a>

                <Popover v-if="canEditWorkspace" align="end" class="ui-popover-form w-56 p-3" aria-label="Move link">
                  <template #trigger
                    ><IconButton title="Move to folder"><FolderIcon class="h-4 w-4" /></IconButton
                  ></template>
                  <Field label="Move to folder">
                    <Select :model-value="link.folder?.id ?? null" @update:model-value="moveLink(link, $event)">
                      <SelectOption :value="null">Unfiled</SelectOption
                      ><SelectOption v-for="folder in folders" :key="folder.id" :value="folder.id">{{
                        folder.name
                      }}</SelectOption>
                    </Select>
                  </Field>
                </Popover>
                <IconButton :title="canEditWorkspace ? 'Edit' : 'View'" @click="selectedLink = link">
                  <Settings2 class="h-4 w-4" />
                </IconButton>
                <IconButton v-if="canEditWorkspace" title="Archive" @click="archiveLink(link)">
                  <Archive class="h-4 w-4" />
                </IconButton>
                <IconButton v-if="canManageWorkspace" variant="danger" title="Delete" @click="deleteLink(link)">
                  <Trash2 class="h-4 w-4" />
                </IconButton>
              </div>
            </article>

            <!-- Global empty state -->
            <div v-if="visibleLinks.length === 0">
              <EmptyState
                :title="
                  hasActiveFilters
                    ? 'No links match'
                    : selectedFolderKey === 'all'
                      ? 'No links yet'
                      : 'This folder is empty'
                "
                :description="
                  hasActiveFilters
                    ? 'Try another search, status, or tag.'
                    : 'Create your first short link to get started.'
                "
              >
                <template #icon><Link2 class="h-5 w-5" /></template>
                <template #action>
                  <Button
                    v-if="canEditWorkspace && !hasActiveFilters"
                    variant="secondary"
                    size="sm"
                    type="button"
                    @click="openCreateLink"
                  >
                    <Plus class="h-3.5 w-3.5" /> New link
                  </Button>
                </template>
              </EmptyState>
            </div>
          </section>
        </div>
      </div>
    </div>

    <Modal
      :show="creatingFolder"
      :title="renamingFolderId === null ? 'New folder' : 'Rename folder'"
      max-width="sm"
      @close="creatingFolder = false"
    >
      <form class="space-y-5 p-6" @submit.prevent="submitFolder">
        <h2 class="text-base font-semibold">{{ renamingFolderId === null ? 'New folder' : 'Rename folder' }}</h2>
        <Field label="Folder name" :error="folderForm.errors.name"
          ><input v-model="folderForm.name" class="h-9" autofocus placeholder="Campaigns, resources…"
        /></Field>
        <div class="flex justify-end gap-2">
          <Button variant="secondary" type="button" @click="creatingFolder = false">Cancel</Button
          ><Button :loading="folderForm.processing">{{
            renamingFolderId === null ? 'Create folder' : 'Save changes'
          }}</Button>
        </div>
      </form>
    </Modal>

    <CreateLinkDrawer
      :show="createOpen"
      :form="linkForm"
      :domains="usableDomains"
      :folders="folders"
      :known-tags="tags"
      :routing-schema="routingSchema"
      @close="createOpen = false"
      @submit="submitLink"
    />

    <EditLinkDrawer
      :link="selectedLink"
      :edit-form="editForm"
      :domains="usableDomains"
      :folders="folders"
      :routing-schema="routingSchema"
      :can-edit-workspace="canEditWorkspace"
      @close="selectedLink = null"
      @submit="updateLink"
    />
    <ConfirmDialog :confirmation="confirmation" @close="confirmation = null" />
  </AuthenticatedLayout>
</template>
