<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
  AlertTriangle,
  ArrowLeft,
  BarChart3,
  Check,
  Copy,
  Download,
  ExternalLink,
  ImagePlus,
  Laptop,
  Loader2,
  Palette,
  QrCode,
  RotateCcw,
  Save,
  Settings2,
  Smartphone,
  Trash2,
  Upload,
} from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

import BioElementList from '@/Components/BioPages/BioElementList.vue';
import BioPagePreview from '@/Components/BioPages/BioPagePreview.vue';
import Button from '@/Components/ui/Button.vue';
import Switch from '@/Components/ui/Switch.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { hasAccessibleContrast, suggestedTextColor } from '@/lib/color';
import type { BioDraft, BioElement, BioPageEditorProps, SaveState } from '@/Pages/BioPages/types';
import { defaultTheme } from '@/Pages/BioPages/types';

const props = withDefaults(defineProps<BioPageEditorProps>(), {
  canEdit: true,
  canPublish: false,
  canDelete: false,
  activeEditors: () => [],
  qrCodes: () => [],
});

function normalizedDraft(source: BioDraft): BioDraft {
  return {
    ...source,
    publicHandle: source.publicHandle ?? '',
    biography: source.biography ?? '',
    shareTitle: source.shareTitle ?? '',
    shareDescription: source.shareDescription ?? '',
    profileImageUrl: source.profileImageUrl ?? null,
    backgroundImageUrl: source.backgroundImageUrl ?? null,
    isIndexable: source.isIndexable ?? true,
    showBranding: source.showBranding ?? true,
    theme: { ...defaultTheme(), ...source.theme },
    elements: (source.elements ?? []).map((element, index) => ({
      ...element,
      clientId: element.clientId || `element-${element.id ?? index}`,
    })),
  };
}

const storageKey = `openlink:bio-page:${props.bioPage.id}:draft`;
const serverDraft = normalizedDraft(props.bioPage.draft);
let restoredDraft: BioDraft | null = null;

try {
  const saved = localStorage.getItem(storageKey);
  if (saved) restoredDraft = normalizedDraft(JSON.parse(saved) as BioDraft);
} catch {
  localStorage.removeItem(storageKey);
}

const draft = reactive<BioDraft>(restoredDraft ?? structuredClone(serverDraft));
const saveState = ref<SaveState>(restoredDraft ? 'failed' : 'idle');
const hasPendingChanges = ref(Boolean(restoredDraft));
const saveMessage = ref(restoredDraft ? 'Recovered local changes. Retry saving to keep them.' : '');
const activeTab = ref<'content' | 'design' | 'settings'>('content');
const previewMode = ref<'mobile' | 'desktop'>('mobile');
const publishing = ref(false);
const unpublishing = ref(false);
const deleting = ref(false);
const uploading = ref<'profile' | 'background' | null>(null);
const copied = ref(false);
let saveTimer: ReturnType<typeof setTimeout> | null = null;
let saveQueued = false;
let saveInFlight = false;
let changeVersion = 0;
let suppressAutosave = true;

const currentDomain = computed(() => props.domains.find((domain) => domain.id === draft.domainId));
const draftBioUrl = computed(() => {
  if (!currentDomain.value || !draft.slug) return props.bioPage.bioUrl;
  return `https://${currentDomain.value.hostname}/${draft.slug.replace(/^\/+/, '')}`;
});
const publishedBioUrl = computed(() => {
  const published = props.bioPage.published;
  const domain = props.domains.find((item) => item.id === published?.domainId);
  return published && domain ? `https://${domain.hostname}/${published.slug.replace(/^\/+/, '')}` : '';
});

function contrastsWithPageBackground(color: string): boolean {
  if (!hasAccessibleContrast(color, draft.theme.backgroundColor)) return false;
  return draft.theme.backgroundType !== 'gradient' || hasAccessibleContrast(color, draft.theme.gradientColor);
}

const textContrastFails = computed(() => !contrastsWithPageBackground(draft.theme.textColor));
const destinationContrastFails = computed(() => {
  if (draft.theme.destinationStyle === 'outline' || draft.theme.destinationStyle === 'transparent') {
    return !contrastsWithPageBackground(draft.theme.destinationTextColor);
  }

  return !hasAccessibleContrast(draft.theme.destinationTextColor, draft.theme.destinationColor);
});

function destinationValid(element: BioElement): boolean {
  if (element.type === 'heading') return Boolean(element.text.trim());
  if (element.type === 'text') return Boolean(element.text.trim());
  if (!element.label.trim()) return false;
  if (element.sourceType === 'short_link') return Boolean(element.shortLinkId);
  if (element.sourceType === 'email') return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(element.url);
  if (element.sourceType === 'telephone') return /^[+\d][\d\s().-]{4,}$/.test(element.url);

  try {
    const url = new URL(element.url);
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}

const publicationErrors = computed(() => {
  const errors: string[] = [];
  if (!draft.displayName.trim()) errors.push('Add a display name.');
  if (!draft.domainId || !draft.slug.trim()) errors.push('Choose a Domain and Slug.');
  if (currentDomain.value?.status !== 'active') errors.push('The selected Domain must be active.');
  const visibleElements = draft.elements.filter((element) => element.visible);
  if (!visibleElements.length) errors.push('Add at least one visible element.');
  if (visibleElements.some((element) => !destinationValid(element))) errors.push('Complete every visible element.');
  if (textContrastFails.value || destinationContrastFails.value) errors.push('Resolve the color contrast issues.');
  if (hasPendingChanges.value || saveState.value === 'saving' || saveState.value === 'failed')
    errors.push('Save the Draft Version before publishing.');
  return errors;
});

const canPublishDraft = computed(() => props.canPublish && publicationErrors.value.length === 0 && !publishing.value);
const saveLabel = computed(
  () =>
    ({
      idle: hasPendingChanges.value ? 'Unsaved changes' : 'No changes',
      saving: 'Saving…',
      saved: 'Saved',
      failed: 'Save failed',
    })[saveState.value],
);

function payload() {
  return {
    domainId: draft.domainId,
    slug: draft.slug,
    displayName: draft.displayName,
    publicHandle: draft.publicHandle,
    biography: draft.biography,
    elements: structuredClone(draft.elements),
    theme: structuredClone(draft.theme),
    shareTitle: draft.shareTitle,
    shareDescription: draft.shareDescription,
    isIndexable: draft.isIndexable,
    showBranding: draft.showBranding,
  };
}

function persistLocally() {
  localStorage.setItem(storageKey, JSON.stringify(draft));
}

function saveDraft() {
  if (!props.canEdit) return;
  if (saveInFlight) {
    saveQueued = true;
    return;
  }

  const savingVersion = changeVersion;
  saveInFlight = true;
  saveState.value = 'saving';
  saveMessage.value = '';
  router.patch(route('bio-pages.update', props.bioPage.id), payload(), {
    preserveScroll: true,
    preserveState: true,
    only: ['bioPage'],
    onSuccess: () => {
      if (changeVersion === savingVersion) {
        hasPendingChanges.value = false;
        saveState.value = 'saved';
        saveMessage.value = '';
        localStorage.removeItem(storageKey);
      }
    },
    onError: (errors) => {
      saveState.value = 'failed';
      saveMessage.value = Object.values(errors)[0] ?? 'Your changes are stored in this browser. Retry when ready.';
      persistLocally();
    },
    onFinish: () => {
      saveInFlight = false;
      if (saveQueued) {
        saveQueued = false;
        saveDraft();
      }
    },
  });
}

watch(
  () => JSON.stringify(payload()),
  () => {
    if (suppressAutosave || !props.canEdit) return;
    changeVersion += 1;
    hasPendingChanges.value = true;
    persistLocally();
    if (!saveInFlight) saveState.value = 'idle';
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveDraft, 700);
  },
);

setTimeout(() => {
  suppressAutosave = false;
}, 0);

function publish() {
  if (!canPublishDraft.value) return;
  publishing.value = true;
  router.post(
    route('bio-pages.publish', props.bioPage.id),
    {},
    {
      preserveScroll: true,
      onFinish: () => (publishing.value = false),
    },
  );
}

function unpublish() {
  if (!confirm('Unpublish this Bio Page? Its Bio URL will become unavailable, but the Draft Version is kept.')) return;
  unpublishing.value = true;
  router.post(
    route('bio-pages.unpublish', props.bioPage.id),
    {},
    { preserveScroll: true, onFinish: () => (unpublishing.value = false) },
  );
}

function deleteBioPage() {
  if (!confirm('Permanently delete this Bio Page? Its Bio URL, media, and Bio Page analytics will be removed.')) return;
  deleting.value = true;
  router.delete(route('bio-pages.destroy', props.bioPage.id), { onFinish: () => (deleting.value = false) });
}

function uploadMedia(type: 'profile' | 'background', event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  const previewUrl = URL.createObjectURL(file);
  if (type === 'profile') draft.profileImageUrl = previewUrl;
  else draft.backgroundImageUrl = previewUrl;

  uploading.value = type;
  router.post(
    route('bio-pages.media.store', props.bioPage.id),
    type === 'profile' ? { profileImage: file } : { backgroundImage: file },
    {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const bioPage = (page.props as unknown as BioPageEditorProps).bioPage;
        if (!bioPage) return;
        suppressAutosave = true;
        if (type === 'profile') draft.profileImageUrl = bioPage.draft.profileImageUrl;
        else draft.backgroundImageUrl = bioPage.draft.backgroundImageUrl;
        setTimeout(() => (suppressAutosave = false), 0);
      },
      onFinish: () => {
        uploading.value = null;
        URL.revokeObjectURL(previewUrl);
        input.value = '';
      },
    },
  );
}

function removeMedia(type: 'profile' | 'background') {
  router.delete(route('bio-pages.media.destroy', { bioPage: props.bioPage.id, type }), {
    preserveScroll: true,
    onSuccess: () => {
      suppressAutosave = true;
      if (type === 'profile') draft.profileImageUrl = null;
      else draft.backgroundImageUrl = null;
      setTimeout(() => (suppressAutosave = false), 0);
    },
  });
}

function applyContrastSuggestion(target: 'text' | 'destination') {
  if (target === 'text') draft.theme.textColor = suggestedTextColor(draft.theme.backgroundColor);
  else {
    const background =
      draft.theme.destinationStyle === 'outline' || draft.theme.destinationStyle === 'transparent'
        ? draft.theme.backgroundColor
        : draft.theme.destinationColor;
    draft.theme.destinationTextColor = suggestedTextColor(background);
  }
}

async function copyBioUrl() {
  if (!draftBioUrl.value) return;
  await navigator.clipboard.writeText(draftBioUrl.value);
  copied.value = true;
  setTimeout(() => (copied.value = false), 1600);
}

function createQrCode() {
  router.post(route('bio-pages.qr-codes.store', props.bioPage.id), {
    name: `${draft.displayName || 'Bio Page'} QR`,
  });
}

function resetLocalDraft() {
  if (!confirm('Discard the locally recovered changes and reload the last saved Draft Version?')) return;
  localStorage.removeItem(storageKey);
  window.location.reload();
}

function warnBeforeUnload(event: BeforeUnloadEvent) {
  if (saveState.value !== 'failed' && saveState.value !== 'saving' && saveState.value !== 'idle') return;
  if (saveState.value === 'idle' && !localStorage.getItem(storageKey)) return;
  event.preventDefault();
}

window.addEventListener('beforeunload', warnBeforeUnload);
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', warnBeforeUnload);
  if (saveTimer) clearTimeout(saveTimer);
});
</script>

<template>
  <Head :title="`${draft.displayName || 'Untitled'} · Bio Page`" />

  <AuthenticatedLayout>
    <div class="flex min-h-[calc(100vh-1px)] flex-col">
      <header
        class="sticky top-0 z-20 flex min-h-14 flex-wrap items-center gap-3 border-b bg-background/90 px-4 py-2 backdrop-blur lg:px-6"
      >
        <Link
          :href="route('bio-pages.index')"
          aria-label="Back to Bio Pages"
          class="grid h-8 w-8 place-items-center rounded-md text-muted hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        >
          <ArrowLeft class="h-4 w-4" />
        </Link>
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold">{{ draft.displayName || 'Untitled Bio Page' }}</p>
          <button
            type="button"
            class="flex max-w-64 items-center gap-1.5 text-xs text-faint hover:text-muted"
            :title="draftBioUrl"
            @click="copyBioUrl"
          >
            <span class="truncate">{{ draftBioUrl || 'Bio URL not configured' }}</span>
            <Check v-if="copied" class="h-3 w-3 text-success" />
            <Copy v-else class="h-3 w-3" />
          </button>
        </div>

        <div class="ml-auto flex items-center gap-2">
          <Link
            :href="route('bio-pages.analytics', bioPage.id)"
            class="hidden h-8 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium text-muted hover:bg-elevated hover:text-foreground md:inline-flex"
          >
            <BarChart3 class="h-3.5 w-3.5" /> Analytics
          </Link>
          <button
            v-if="canEdit"
            type="button"
            class="hidden h-8 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium text-muted hover:bg-elevated hover:text-foreground md:inline-flex"
            @click="createQrCode"
          >
            <QrCode class="h-3.5 w-3.5" /> QR Code
          </button>
          <span
            class="hidden items-center gap-1.5 text-xs sm:inline-flex"
            :class="saveState === 'failed' ? 'text-danger' : 'text-faint'"
            :title="saveMessage"
          >
            <Loader2 v-if="saveState === 'saving'" class="h-3.5 w-3.5 animate-spin" />
            <AlertTriangle v-else-if="saveState === 'failed'" class="h-3.5 w-3.5" />
            <Save v-else class="h-3.5 w-3.5" />
            {{ saveLabel }}
          </span>
          <Button v-if="saveState === 'failed'" type="button" size="sm" variant="secondary" @click="saveDraft">
            <RotateCcw class="h-3.5 w-3.5" /> Retry
          </Button>
          <Button
            v-if="canPublish"
            type="button"
            size="sm"
            :loading="publishing"
            :disabled="!canPublishDraft"
            :title="publicationErrors.join(' ')"
            @click="publish"
          >
            Publish
          </Button>
        </div>
      </header>

      <div v-if="activeEditors.length" class="border-b border-warning/20 bg-warning/10 px-5 py-2 text-xs text-warning">
        {{ activeEditors.join(', ') }} {{ activeEditors.length === 1 ? 'is' : 'are' }} also editing. The latest
        successful save wins.
      </div>
      <div
        v-if="saveState === 'failed'"
        class="flex items-center gap-3 border-b border-danger/20 bg-danger/10 px-5 py-2 text-xs text-danger"
      >
        <span class="min-w-0 flex-1">{{ saveMessage }}</span>
        <button type="button" class="shrink-0 font-semibold hover:underline" @click="resetLocalDraft">
          Discard local copy
        </button>
      </div>

      <div class="grid flex-1 xl:grid-cols-[minmax(480px,0.9fr)_minmax(460px,1.1fr)]">
        <section class="min-w-0 border-b xl:border-b-0 xl:border-r">
          <div class="sticky top-14 z-10 flex border-b bg-background px-4 sm:px-6">
            <button
              v-for="tab in [
                { value: 'content', label: 'Content', icon: ImagePlus },
                { value: 'design', label: 'Design', icon: Palette },
                { value: 'settings', label: 'Settings', icon: Settings2 },
              ]"
              :key="tab.value"
              type="button"
              class="flex h-11 items-center gap-1.5 border-b-2 px-3 text-[13px] font-medium transition"
              :class="
                activeTab === tab.value
                  ? 'border-accent text-foreground'
                  : 'border-transparent text-faint hover:text-muted'
              "
              @click="activeTab = tab.value as typeof activeTab"
            >
              <component :is="tab.icon" class="h-3.5 w-3.5" /> {{ tab.label }}
            </button>
          </div>

          <div class="mx-auto grid max-w-3xl gap-7 p-4 sm:p-6 lg:p-8">
            <template v-if="activeTab === 'content'">
              <section class="card-sheen grid gap-4 rounded-lg border bg-surface p-4">
                <div>
                  <h2 class="text-sm font-semibold">Profile</h2>
                  <p class="mt-0.5 text-xs text-faint">Shown at the top of your Bio Page.</p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                  <span
                    class="grid h-16 w-16 place-items-center overflow-hidden rounded-full border bg-elevated text-lg font-semibold"
                  >
                    <img
                      v-if="draft.profileImageUrl"
                      :src="draft.profileImageUrl"
                      alt=""
                      class="h-full w-full object-cover"
                    />
                    <span v-else>{{ draft.displayName.charAt(0).toUpperCase() || '?' }}</span>
                  </span>
                  <div class="flex flex-wrap gap-2">
                    <label
                      class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border px-2.5 text-[13px] font-medium hover:border-border-strong"
                    >
                      <Loader2 v-if="uploading === 'profile'" class="h-3.5 w-3.5 animate-spin" />
                      <Upload v-else class="h-3.5 w-3.5" /> Upload photo
                      <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        :disabled="!canEdit"
                        @change="uploadMedia('profile', $event)"
                      />
                    </label>
                    <button
                      v-if="draft.profileImageUrl"
                      type="button"
                      class="px-2 text-xs text-danger hover:underline"
                      @click="removeMedia('profile')"
                    >
                      Remove
                    </button>
                  </div>
                </div>
                <label class="grid gap-1.5 text-[13px] font-medium">
                  Display name
                  <input v-model="draft.displayName" maxlength="80" :disabled="!canEdit" />
                  <span class="text-right text-[11px] font-normal text-faint">{{ draft.displayName.length }}/80</span>
                </label>
                <label class="grid gap-1.5 text-[13px] font-medium">
                  Public handle <span class="font-normal text-faint">Optional</span>
                  <input v-model="draft.publicHandle" maxlength="30" placeholder="@alice" :disabled="!canEdit" />
                </label>
                <label class="grid gap-1.5 text-[13px] font-medium">
                  Biography <span class="font-normal text-faint">Optional</span>
                  <textarea v-model="draft.biography" maxlength="160" rows="3" :disabled="!canEdit" />
                  <span class="text-right text-[11px] font-normal text-faint">{{ draft.biography.length }}/160</span>
                </label>
              </section>

              <BioElementList v-model:elements="draft.elements" :short-links="shortLinks" :disabled="!canEdit" />
            </template>

            <template v-else-if="activeTab === 'design'">
              <section class="card-sheen grid gap-5 rounded-lg border bg-surface p-4">
                <div>
                  <h2 class="text-sm font-semibold">Theme</h2>
                  <p class="mt-0.5 text-xs text-faint">
                    Controlled choices keep every public page responsive and readable.
                  </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                  <label class="grid gap-1.5 text-[13px] font-medium">
                    Appearance
                    <select v-model="draft.theme.appearance" :disabled="!canEdit">
                      <option value="light">Light</option>
                      <option value="dark">Dark</option>
                      <option value="auto">Automatic</option>
                    </select>
                  </label>
                  <label class="grid gap-1.5 text-[13px] font-medium">
                    Font
                    <select v-model="draft.theme.fontFamily" :disabled="!canEdit">
                      <option value="sans">Sans</option>
                      <option value="serif">Serif</option>
                      <option value="rounded">Rounded</option>
                      <option value="mono">Mono</option>
                    </select>
                  </label>
                  <label class="grid gap-1.5 text-[13px] font-medium">
                    Background
                    <select v-model="draft.theme.backgroundType" :disabled="!canEdit">
                      <option value="color">Color</option>
                      <option value="gradient">Gradient</option>
                      <option value="image">Image</option>
                    </select>
                  </label>
                  <label class="grid gap-1.5 text-[13px] font-medium">
                    Background color
                    <span class="flex gap-2"
                      ><input
                        v-model="draft.theme.backgroundColor"
                        type="color"
                        class="h-9 w-12"
                        :disabled="!canEdit" /><input
                        v-model="draft.theme.backgroundColor"
                        maxlength="7"
                        :disabled="!canEdit"
                    /></span>
                  </label>
                  <label v-if="draft.theme.backgroundType === 'gradient'" class="grid gap-1.5 text-[13px] font-medium">
                    Gradient color
                    <span class="flex gap-2"
                      ><input
                        v-model="draft.theme.gradientColor"
                        type="color"
                        class="h-9 w-12"
                        :disabled="!canEdit" /><input
                        v-model="draft.theme.gradientColor"
                        maxlength="7"
                        :disabled="!canEdit"
                    /></span>
                  </label>
                  <label class="grid gap-1.5 text-[13px] font-medium">
                    Text color
                    <span class="flex gap-2"
                      ><input
                        v-model="draft.theme.textColor"
                        type="color"
                        class="h-9 w-12"
                        :disabled="!canEdit" /><input
                        v-model="draft.theme.textColor"
                        maxlength="7"
                        :disabled="!canEdit"
                    /></span>
                    <span
                      v-if="textContrastFails"
                      class="flex items-center justify-between gap-2 text-xs font-normal text-danger"
                    >
                      Contrast is too low.
                      <button type="button" class="font-semibold underline" @click="applyContrastSuggestion('text')">
                        Use accessible color
                      </button>
                    </span>
                  </label>
                </div>

                <div v-if="draft.theme.backgroundType === 'image'" class="grid gap-3 rounded-lg border p-3">
                  <div class="flex flex-wrap items-center gap-2">
                    <label
                      class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border px-2.5 text-[13px] font-medium hover:border-border-strong"
                    >
                      <Loader2 v-if="uploading === 'background'" class="h-3.5 w-3.5 animate-spin" />
                      <Upload v-else class="h-3.5 w-3.5" /> Upload background
                      <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        :disabled="!canEdit"
                        @change="uploadMedia('background', $event)"
                      />
                    </label>
                    <button
                      v-if="draft.backgroundImageUrl"
                      type="button"
                      class="px-2 text-xs text-danger hover:underline"
                      @click="removeMedia('background')"
                    >
                      Remove
                    </button>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-[13px] font-medium"
                      >Image fit<select v-model="draft.theme.imageFit">
                        <option value="cover">Cover</option>
                        <option value="contain">Contain</option>
                      </select></label
                    >
                    <label class="grid gap-1.5 text-[13px] font-medium"
                      >Readability overlay · {{ draft.theme.overlayOpacity }}%<input
                        v-model.number="draft.theme.overlayOpacity"
                        type="range"
                        min="0"
                        max="80"
                    /></label>
                  </div>
                </div>
              </section>

              <section class="card-sheen grid gap-4 rounded-lg border bg-surface p-4">
                <h2 class="text-sm font-semibold">Destinations</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                  <label class="grid gap-1.5 text-[13px] font-medium"
                    >Style<select v-model="draft.theme.destinationStyle">
                      <option value="solid">Solid</option>
                      <option value="outline">Outline</option>
                      <option value="soft">Soft</option>
                      <option value="transparent">Transparent</option>
                    </select></label
                  >
                  <label class="grid gap-1.5 text-[13px] font-medium"
                    >Corners<select v-model="draft.theme.destinationRadius">
                      <option value="square">Square</option>
                      <option value="rounded">Rounded</option>
                      <option value="large">Very rounded</option>
                      <option value="pill">Pill</option>
                    </select></label
                  >
                  <label class="grid gap-1.5 text-[13px] font-medium"
                    >Destination color<span class="flex gap-2"
                      ><input v-model="draft.theme.destinationColor" type="color" class="h-9 w-12" /><input
                        v-model="draft.theme.destinationColor"
                        maxlength="7" /></span
                  ></label>
                  <label class="grid gap-1.5 text-[13px] font-medium"
                    >Destination text<span class="flex gap-2"
                      ><input v-model="draft.theme.destinationTextColor" type="color" class="h-9 w-12" /><input
                        v-model="draft.theme.destinationTextColor"
                        maxlength="7" /></span
                    ><span
                      v-if="destinationContrastFails"
                      class="flex items-center justify-between gap-2 text-xs font-normal text-danger"
                      >Contrast is too low.
                      <button
                        type="button"
                        class="font-semibold underline"
                        @click="applyContrastSuggestion('destination')"
                      >
                        Use accessible color
                      </button></span
                    ></label
                  >
                  <label class="grid gap-1.5 text-[13px] font-medium"
                    >Profile image shape<select v-model="draft.theme.profileShape">
                      <option value="circle">Circle</option>
                      <option value="rounded">Rounded square</option>
                      <option value="square">Square</option>
                    </select></label
                  >
                  <label class="flex items-center justify-between gap-4 text-[13px] font-medium"
                    >Destination shadow<Switch v-model="draft.theme.destinationShadow"
                  /></label>
                </div>
              </section>
            </template>

            <template v-else>
              <section class="card-sheen grid gap-4 rounded-lg border bg-surface p-4">
                <div>
                  <h2 class="text-sm font-semibold">Bio URL</h2>
                  <p class="mt-0.5 text-xs text-faint">Changes take effect atomically on your next publication.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_1fr]">
                  <label class="grid gap-1.5 text-[13px] font-medium"
                    >Domain<select v-model.number="draft.domainId" :disabled="!canEdit">
                      <option :value="null">Select a Domain…</option>
                      <option v-for="domain in domains" :key="domain.id" :value="domain.id">
                        {{ domain.hostname }} · {{ domain.status }}
                      </option>
                    </select></label
                  >
                  <label class="grid gap-1.5 text-[13px] font-medium"
                    >Slug<input v-model="draft.slug" maxlength="160" placeholder="alice" :disabled="!canEdit"
                  /></label>
                </div>
                <p
                  v-if="bioPage.publishedAt && draftBioUrl !== publishedBioUrl"
                  class="flex gap-2 rounded-md bg-warning/10 p-3 text-xs text-warning"
                >
                  <AlertTriangle class="h-4 w-4 shrink-0" /> The old Bio URL will stop working when this Draft Version
                  is published.
                </p>
              </section>

              <section class="card-sheen grid gap-4 rounded-lg border bg-surface p-4">
                <div>
                  <h2 class="text-sm font-semibold">Sharing and discovery</h2>
                  <p class="mt-0.5 text-xs text-faint">Open Graph imagery is generated from the profile and theme.</p>
                </div>
                <label class="grid gap-1.5 text-[13px] font-medium"
                  >Share title <span class="font-normal text-faint">Optional</span
                  ><input v-model="draft.shareTitle" maxlength="80" :placeholder="draft.displayName"
                /></label>
                <label class="grid gap-1.5 text-[13px] font-medium"
                  >Share description <span class="font-normal text-faint">Optional</span
                  ><textarea v-model="draft.shareDescription" maxlength="160" rows="3" :placeholder="draft.biography" />
                </label>
                <label class="flex items-center justify-between gap-4 text-[13px]"
                  ><span
                    ><span class="block font-medium">Allow search indexing</span
                    ><span class="block text-xs text-faint"
                      >Search engines may include the Published Version.</span
                    ></span
                  ><Switch v-model="draft.isIndexable"
                /></label>
                <label class="flex items-center justify-between gap-4 text-[13px]"
                  ><span
                    ><span class="block font-medium">Show Openlink branding</span
                    ><span class="block text-xs text-faint"
                      >Displays a discreet “Powered by Openlink” footer.</span
                    ></span
                  ><Switch v-model="draft.showBranding"
                /></label>
              </section>

              <section class="card-sheen grid gap-4 rounded-lg border bg-surface p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h2 class="text-sm font-semibold">QR Codes</h2>
                    <p class="mt-0.5 text-xs text-faint">Scannable entry points attached to this Bio Page.</p>
                  </div>
                  <Button v-if="canEdit" type="button" size="sm" variant="secondary" @click="createQrCode">
                    <QrCode class="h-3.5 w-3.5" /> New QR Code
                  </Button>
                </div>

                <p
                  v-if="qrCodes.length === 0"
                  class="rounded-md border border-dashed p-4 text-center text-xs text-faint"
                >
                  No QR Codes are attached yet.
                </p>

                <div v-else class="divide-y rounded-md border">
                  <div v-for="qr in qrCodes" :key="qr.id" class="flex flex-wrap items-center gap-3 px-3 py-2.5">
                    <QrCode class="h-4 w-4 shrink-0 text-faint" />
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-[13px] font-medium text-foreground">{{ qr.name }}</p>
                      <p class="truncate font-mono text-[11px] text-faint">{{ qr.public_url }}</p>
                    </div>
                    <span class="text-xs tabular-nums text-faint">{{ qr.scans }} scans</span>
                    <template v-if="canEdit">
                      <Link
                        :href="route('qr-codes.show', qr.token)"
                        class="inline-flex h-7 items-center rounded-md border px-2 text-xs font-medium text-muted hover:border-border-strong hover:text-foreground"
                      >
                        Open studio
                      </Link>
                      <a
                        :href="route('qr-codes.export', [qr.token, 'png'])"
                        class="grid h-7 w-7 place-items-center rounded-md border text-muted hover:border-border-strong hover:text-foreground"
                        :aria-label="`Download ${qr.name} as PNG`"
                        title="Download PNG"
                      >
                        <Download class="h-3.5 w-3.5" />
                      </a>
                      <a
                        :href="route('qr-codes.export', [qr.token, 'svg'])"
                        class="grid h-7 w-7 place-items-center rounded-md border text-muted hover:border-border-strong hover:text-foreground"
                        :aria-label="`Download ${qr.name} as SVG`"
                        title="Download SVG"
                      >
                        <Download class="h-3.5 w-3.5" />
                      </a>
                    </template>
                  </div>
                </div>
              </section>

              <section v-if="canPublish || canDelete" class="card-sheen grid gap-4 rounded-lg border bg-surface p-4">
                <div>
                  <h2 class="text-sm font-semibold">Publication</h2>
                  <p class="mt-0.5 text-xs text-faint">The Draft Version is kept when a page is unpublished.</p>
                </div>
                <div v-if="publicationErrors.length" class="rounded-md bg-warning/10 p-3 text-xs text-warning">
                  <p class="font-semibold">Before publishing:</p>
                  <ul class="mt-1 list-disc space-y-0.5 pl-4">
                    <li v-for="error in publicationErrors" :key="error">{{ error }}</li>
                  </ul>
                </div>
                <div class="flex flex-wrap gap-2">
                  <Button
                    v-if="canPublish"
                    type="button"
                    size="sm"
                    :disabled="!canPublishDraft"
                    :loading="publishing"
                    @click="publish"
                    >Publish Draft Version</Button
                  >
                  <Button
                    v-if="bioPage.publishedAt && canPublish"
                    type="button"
                    size="sm"
                    variant="secondary"
                    :loading="unpublishing"
                    @click="unpublish"
                    >Unpublish</Button
                  >
                  <a
                    v-if="bioPage.publishedAt && publishedBioUrl"
                    :href="publishedBioUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium text-muted hover:bg-elevated hover:text-foreground"
                    >View published page <ExternalLink class="h-3.5 w-3.5"
                  /></a>
                </div>
              </section>

              <section v-if="canDelete" class="grid gap-3 rounded-lg border border-danger/20 bg-danger/5 p-4">
                <div>
                  <h2 class="text-sm font-semibold text-danger">Delete permanently</h2>
                  <p class="mt-1 text-xs text-muted">
                    The Bio URL becomes unavailable immediately and its Slug may be reused.
                  </p>
                </div>
                <Button
                  type="button"
                  size="sm"
                  variant="danger"
                  class="w-fit"
                  :loading="deleting"
                  @click="deleteBioPage"
                  ><Trash2 class="h-3.5 w-3.5" /> Delete Bio Page</Button
                >
              </section>
            </template>
          </div>
        </section>

        <aside class="relative min-h-[680px] bg-elevated/30 p-4 sm:p-8 xl:min-h-0">
          <div class="sticky top-20 flex flex-col items-center">
            <div class="mb-4 flex rounded-lg border bg-surface p-1">
              <button
                type="button"
                class="grid h-8 w-9 place-items-center rounded-md"
                :class="previewMode === 'mobile' ? 'bg-elevated text-foreground' : 'text-faint'"
                aria-label="Mobile preview"
                @click="previewMode = 'mobile'"
              >
                <Smartphone class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="grid h-8 w-9 place-items-center rounded-md"
                :class="previewMode === 'desktop' ? 'bg-elevated text-foreground' : 'text-faint'"
                aria-label="Desktop preview"
                @click="previewMode = 'desktop'"
              >
                <Laptop class="h-4 w-4" />
              </button>
            </div>
            <div
              class="overflow-hidden border border-border-strong bg-black shadow-2xl transition-all duration-300"
              :class="
                previewMode === 'mobile'
                  ? 'h-[700px] w-full max-w-[390px] rounded-[2rem]'
                  : 'h-[680px] w-full max-w-[860px] rounded-xl'
              "
            >
              <BioPagePreview :page="draft" :bio-url="draftBioUrl" compact />
            </div>
            <p v-if="!bioPage.publishedAt" class="mt-3 text-center text-xs text-faint">
              Not published · this preview is visible only to workspace members
            </p>
          </div>
        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
