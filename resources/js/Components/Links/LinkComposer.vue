<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CornerDownLeft } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import Button from '@/Components/ui/Button.vue';
import { isLikelyUrl, normalizeUrl } from '@/lib/links';
import {
  folderField,
  newQuickLinkForm,
  initialDomainId,
  selectableDomains,
  toPayload,
} from '@/lib/shortLinks/shortLinkForm';
import { copyToClipboard } from '@/lib/toast';
import type { Domain, Folder } from '@/types/payloads';

import type { ComposerLink } from './composerLink';
import ComposerOptions from './ComposerOptions.vue';
import CreatedLinkBanner from './CreatedLinkBanner.vue';
import { useUrlPasteCapture } from './useUrlPasteCapture';

export type { ComposerLink } from './composerLink';

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

const usableDomains = computed(() => selectableDomains(props.domains));
const initialDomain = () => initialDomainId(usableDomains.value, props.preferredDomainId);

const form = useForm(newQuickLinkForm(initialDomain(), props.folderId));

watch(
  () => props.folderId,
  (id) => (form.folder_id = folderField(id)),
);

const input = ref<HTMLInputElement | null>(null);
const focused = ref(false);
const root = ref<HTMLFormElement | null>(null);
const created = ref<ComposerLink | null>(null);
const copied = ref(false);

function onFocusOut() {
  requestAnimationFrame(() => {
    const active = document.activeElement;
    const insideForm = Boolean(active && root.value?.contains(active));
    const insidePopover = Boolean(active?.closest('[data-radix-popper-content-wrapper]'));
    focused.value = insideForm || insidePopover;
  });
}

const normalized = computed(() => normalizeUrl(form.destination_url));
const valid = computed(() => isLikelyUrl(normalized.value));
const expanded = computed(() => focused.value || form.destination_url !== '' || form.slug !== '');
const selectedDomain = computed(() => usableDomains.value.find((domain) => domain.id === Number(form.domain_id)));
const error = computed(() => form.errors.destination_url ?? form.errors.slug ?? form.errors.domain_id);

function submit() {
  if (!valid.value || form.processing) return;

  form
    .transform((data) => toPayload(data))
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

useUrlPasteCapture(
  () => props.capturePaste,
  (url) => {
    form.destination_url = url;
    created.value = null;
    nextTick(focus);
  },
);

function clear() {
  form.reset('destination_url', 'slug');
  form.clearErrors();
  input.value?.blur();
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
  if (props.autofocus) requestAnimationFrame(focus);
});

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
        @keydown.escape="clear"
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
        <ComposerOptions
          v-model:domain-id="form.domain_id"
          v-model:slug="form.slug"
          v-model:folder-id="form.folder_id"
          :domains="usableDomains"
          :folders="folders"
          :error="error"
        />
      </div>
    </div>

    <Transition
      enter-active-class="transition duration-300 ease-emphasized-out"
      enter-from-class="-translate-y-1 opacity-0"
      leave-active-class="transition duration-150 ease-out"
      leave-to-class="opacity-0"
    >
      <CreatedLinkBanner
        v-if="created"
        :link="created"
        :copied="copied"
        @copy="copyCreated"
        @edit="emit('edit', created)"
        @dismiss="created = null"
      />
    </Transition>
  </form>
</template>
