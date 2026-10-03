<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, Check, Copy, CornerDownLeft, Dices, PencilLine, QrCode, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import Button from '@/Components/ui/Button.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import Select from '@/Components/ui/Select.vue';
import { createQrCodeFor } from '@/lib/linkActions';
import { displayUrl, isLikelyUrl, normalizeUrl, randomSlug } from '@/lib/links';
import { copyToClipboard } from '@/lib/toast';

export type ComposerLink = { id: number; short_url: string; destination_url: string; slug: string };

type Domain = { id: number; hostname: string; status: string; is_default: boolean };
type Folder = { id: number; name: string };

const props = withDefaults(
  defineProps<{
    domains: Domain[];
    folders?: Folder[];
    preferredDomainId?: number | null;
    folderId?: number | null;
    capturePaste?: boolean;
    autofocus?: boolean;
    size?: 'md' | 'lg';
  }>(),
  { folders: () => [], preferredDomainId: null, folderId: null, capturePaste: true, autofocus: false, size: 'md' },
);

const emit = defineEmits<{ created: [link: ComposerLink]; edit: [link: ComposerLink] }>();

const usableDomains = computed(() => props.domains.filter((domain) => domain.status === 'active'));
const initialDomain = () =>
  usableDomains.value.find((domain) => domain.id === props.preferredDomainId)?.id ?? usableDomains.value[0]?.id ?? '';

const form = useForm({
  destination_url: '',
  domain_id: initialDomain() as number | string,
  folder_id: props.folderId ? String(props.folderId) : '',
  slug: '',
  is_enabled: true,
});

watch(
  () => props.folderId,
  (id) => (form.folder_id = id ? String(id) : ''),
);

const input = ref<HTMLInputElement | null>(null);
const focused = ref(false);
const root = ref<HTMLFormElement | null>(null);

function onFocusOut() {
  requestAnimationFrame(() => {
    const active = document.activeElement;
    const insideForm = Boolean(active && root.value?.contains(active));
    const insidePopover = Boolean(active?.closest('[data-radix-popper-content-wrapper]'));
    focused.value = insideForm || insidePopover;
  });
}
const created = ref<ComposerLink | null>(null);
const copied = ref(false);

const normalized = computed(() => normalizeUrl(form.destination_url));
const valid = computed(() => isLikelyUrl(normalized.value));
const expanded = computed(() => focused.value || form.destination_url !== '' || form.slug !== '');
const selectedDomain = computed(() => usableDomains.value.find((domain) => domain.id === Number(form.domain_id)));
const domainOptions = computed(() =>
  usableDomains.value.map((domain) => ({ value: domain.id, label: domain.hostname })),
);
const folderOptions = computed(() => [
  { value: '', label: 'No folder' },
  ...props.folders.map((folder) => ({ value: String(folder.id), label: folder.name })),
]);
const error = computed(() => form.errors.destination_url ?? form.errors.slug ?? form.errors.domain_id);

function submit() {
  if (!valid.value || form.processing) return;

  form
    .transform((data) => ({ ...data, destination_url: normalized.value }))
    .post(route('short-links.store'), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: async (page) => {
        const link = (page.flash as { createdLink?: ComposerLink }).createdLink;
        form.reset('destination_url', 'slug');
        if (!link) return;
        created.value = link;
        copied.value = await copyToClipboard(link.short_url, 'Short link created and copied', 'Short link created');
        emit('created', link);
      },
    });
}

async function copyCreated() {
  if (!created.value) return;
  copied.value = await copyToClipboard(created.value.short_url);
}

function focus() {
  input.value?.focus();
}

function onDocumentPaste(event: ClipboardEvent) {
  if (!props.capturePaste) return;
  const target = event.target as HTMLElement | null;
  if (
    target &&
    (target.closest('input, textarea, select, [contenteditable="true"]') || target.closest('[role="dialog"]'))
  ) {
    return;
  }

  const text = event.clipboardData?.getData('text') ?? '';
  if (!isLikelyUrl(normalizeUrl(text))) return;

  event.preventDefault();
  form.destination_url = text.trim();
  created.value = null;
  nextTick(focus);
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    form.reset('destination_url', 'slug');
    form.clearErrors();
    input.value?.blur();
  }
}

watch(
  () => form.destination_url,
  (value) => {
    if (value !== '') created.value = null;
    if (form.errors.destination_url) form.clearErrors('destination_url');
  },
);

watch(usableDomains, () => {
  if (!selectedDomain.value) form.domain_id = initialDomain();
});

onMounted(() => {
  document.addEventListener('paste', onDocumentPaste);
  if (props.autofocus) requestAnimationFrame(focus);
});
onUnmounted(() => document.removeEventListener('paste', onDocumentPaste));

defineExpose({ focus });
</script>

<template>
  <form
    ref="root"
    class="overflow-hidden rounded-xl border bg-surface transition-[border-color,box-shadow] duration-200"
    :class="focused ? 'border-border-strong' : ''"
    @focusin="focused = true"
    @focusout="onFocusOut"
    @submit.prevent="submit"
  >
    <div class="flex items-center gap-2.5 pl-3.5 pr-1.5" :class="size === 'lg' ? 'h-12' : 'h-11'">
      <Favicon :url="normalized" size="sm" />
      <input
        ref="input"
        v-model="form.destination_url"
        type="text"
        inputmode="url"
        autocomplete="off"
        spellcheck="false"
        aria-label="Destination URL"
        class="h-full min-w-0 flex-1 bg-transparent text-foreground outline-none placeholder:text-faint"
        :class="size === 'lg' ? 'text-[15px]' : 'text-sm'"
        placeholder="Paste a long URL to shorten it"
        @keydown="onKeydown"
      />
      <Button size="md" :loading="form.processing" :disabled="!valid || usableDomains.length === 0" class="shrink-0">
        Shorten
        <CornerDownLeft v-if="!form.processing" class="h-3.5 w-3.5 opacity-60" />
      </Button>
    </div>

    <div
      class="ease-emphasized-out grid transition-[grid-template-rows] duration-300"
      :class="expanded && !created ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
    >
      <div class="min-h-0 overflow-hidden">
        <div class="flex flex-wrap items-center gap-1.5 border-t px-2.5 py-2">
          <div
            class="flex h-7 min-w-0 flex-1 items-center rounded-lg border border-transparent bg-elevated/70 transition-colors focus-within:border-accent/60 sm:max-w-sm"
          >
            <Select
              v-model="form.domain_id"
              :options="domainOptions"
              size="sm"
              aria-label="Domain"
              class="h-full w-auto max-w-[55%] rounded-l-lg rounded-r-none border-0 border-r bg-transparent font-medium hover:border-r-border focus-visible:ring-0"
            />
            <span class="px-1.5 font-mono text-[13px] text-faint">/</span>
            <input
              v-model="form.slug"
              type="text"
              spellcheck="false"
              autocomplete="off"
              aria-label="Custom slug"
              placeholder="auto"
              class="h-full min-w-0 flex-1 bg-transparent font-mono text-[13px] text-foreground outline-none placeholder:text-faint"
            />
            <button
              type="button"
              class="grid h-full w-7 shrink-0 place-items-center rounded-r-lg text-faint transition-colors hover:text-foreground"
              title="Random slug"
              aria-label="Random slug"
              @mousedown.prevent
              @click="form.slug = randomSlug()"
            >
              <Dices class="h-3.5 w-3.5" />
            </button>
          </div>
          <Select
            v-if="folders.length"
            v-model="form.folder_id"
            :options="folderOptions"
            size="sm"
            aria-label="Folder"
            class="w-auto min-w-32 max-w-48"
          />
          <p v-if="error" class="basis-full px-1 text-xs text-danger">{{ error }}</p>
          <p v-else-if="usableDomains.length === 0" class="basis-full px-1 text-xs text-warning">
            Add and verify a domain before creating links.
          </p>
          <p v-else class="ml-auto hidden items-center gap-1.5 text-xs text-faint lg:flex">
            <Kbd>↵</Kbd> to shorten · <Kbd>Esc</Kbd> to clear
          </p>
        </div>
      </div>
    </div>

    <Transition
      enter-active-class="transition duration-300 ease-emphasized-out"
      enter-from-class="-translate-y-1 opacity-0"
      leave-active-class="transition duration-150 ease-out"
      leave-to-class="opacity-0"
    >
      <div v-if="created" class="flex flex-wrap items-center gap-2.5 border-t px-3.5 py-2.5">
        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-success/15 text-success">
          <Check class="h-3.5 w-3.5" />
        </span>
        <div class="min-w-0 flex-1">
          <a
            :href="created.short_url"
            target="_blank"
            rel="noopener"
            class="block truncate text-[13px] font-semibold text-foreground hover:text-accent"
            >{{ displayUrl(created.short_url) }}</a
          >
          <p class="truncate text-xs text-faint">
            <ArrowRight class="mr-1 inline h-3 w-3" />{{ displayUrl(created.destination_url) }}
          </p>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <Button variant="secondary" size="sm" type="button" @click="copyCreated">
            <component :is="copied ? Check : Copy" class="h-3.5 w-3.5" />
            {{ copied ? 'Copied' : 'Copy' }}
          </Button>
          <Button variant="ghost" size="sm" type="button" @click="emit('edit', created)">
            <PencilLine class="h-3.5 w-3.5" /> Details
          </Button>
          <Button
            variant="ghost"
            size="sm"
            type="button"
            class="hidden sm:inline-flex"
            @click="createQrCodeFor(created)"
          >
            <QrCode class="h-3.5 w-3.5" /> QR code
          </Button>
          <button
            type="button"
            class="grid h-8 w-8 place-items-center rounded-lg text-faint transition-colors hover:bg-elevated hover:text-foreground"
            aria-label="Dismiss"
            @click="created = null"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </Transition>
  </form>
</template>
