<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
  AlertTriangle,
  ArrowLeft,
  Copy,
  Download,
  FileText,
  ImageOff,
  Link2,
  MoreHorizontal,
  Trash2,
  Upload,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmAction } from '@/lib/confirm';
import type { SelectOption } from '@/lib/controls';
import { displayUrl } from '@/lib/links';
import { copyToClipboard, toast } from '@/lib/toast';

import PayloadFields from './PayloadFields.vue';
import ShortLinkPicker from './ShortLinkPicker.vue';
import type { PayloadDescriptors, QrCodeRecord, ShortLinkOption } from './types';
import { payloadDefaults } from './types';
import { useShortLinkSearch } from './useShortLinkSearch';

type TargetType = 'short_link' | 'direct';

const props = defineProps<{
  qr: QrCodeRecord;
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
  shortLinks: ShortLinkOption[];
  canEditWorkspace: boolean;
}>();

const STYLES = [
  { value: 'square', label: 'Squares' },
  { value: 'rounded', label: 'Rounded' },
  { value: 'dot', label: 'Dots' },
];

const EYE_STYLES = [
  { value: 'square', label: 'Square' },
  { value: 'rounded', label: 'Rounded' },
  { value: 'circle', label: 'Circle' },
];

const EXPORT_SIZE_OPTIONS: SelectOption<number>[] = [512, 1024, 2048, 4096].map((size) => ({
  value: size,
  label: `${size} px`,
}));

const TARGET_OPTIONS: { value: TargetType; label: string; icon: unknown }[] = [
  { value: 'short_link', label: 'Short link', icon: Link2 },
  { value: 'direct', label: 'Content', icon: FileText },
];

const ERROR_CORRECTION_OPTIONS: SelectOption[] = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'quartile', label: 'Quartile' },
  { value: 'high', label: 'High' },
];

const typeOptions: SelectOption[] = Object.entries(props.payloadTypes).map(([value, label]) => ({ value, label }));
const originalWasDirect = props.qr.is_direct;

const form = useForm({
  name: props.qr.name,
  target_type: (props.qr.is_direct ? 'direct' : 'short_link') as TargetType,
  short_link_id: props.qr.short_link_id ?? ('' as string | number),
  payload_type: props.qr.payload_type ?? 'url',
  payload: {
    ...payloadDefaults(props.qr.payload_type ?? 'url', props.payloadDescriptors),
    ...props.qr.payload,
  },
  size: props.qr.size,
  foreground_color: props.qr.foreground_color,
  background_color: props.qr.background_color,
  margin: props.qr.margin,
  error_correction: props.qr.error_correction,
  style: props.qr.style,
  eye_style: props.qr.eye_style,
  background_transparent: props.qr.background_transparent,
  logo: null as File | null,
  remove_logo: false,
});

const exportSize = ref(props.qr.size);

const { search: shortLinkSearch, links: availableShortLinks } = useShortLinkSearch(
  props.shortLinks,
  computed(() => form.short_link_id),
);
const previewVersion = ref(0);
const logoInput = ref<HTMLInputElement | null>(null);

const previewUrl = computed(() => {
  const params = new URLSearchParams({
    size: '512',
    foreground_color: form.foreground_color,
    background_color: form.background_color,
    margin: String(form.margin),
    error_correction: form.error_correction,
    style: form.style,
    eye_style: form.eye_style,
    background_transparent: form.background_transparent ? '1' : '0',
    v: String(previewVersion.value),
  });

  return `${route('qr-codes.preview', props.qr.token)}?${params.toString()}`;
});

type Panel = 'content' | 'style' | 'advanced';

const PANELS: { value: Panel; label: string }[] = [
  { value: 'content', label: 'Content' },
  { value: 'style', label: 'Style' },
  { value: 'advanced', label: 'Advanced' },
];

const panel = ref<Panel>('content');

const PANEL_FIELDS: Record<Panel, string[]> = {
  content: ['name', 'short_link_id', 'payload_type', 'payload'],
  style: ['style', 'eye_style', 'foreground_color', 'background_color', 'background_transparent', 'logo'],
  advanced: ['margin', 'error_correction', 'size'],
};

function revealErrors() {
  const keys = Object.keys(form.errors);
  const target = PANELS.find(({ value }) =>
    keys.some((key) => PANEL_FIELDS[value].some((field) => key === field || key.startsWith(`${field}.`))),
  );
  if (target) panel.value = target.value;
}

const isDirty = computed(() => form.isDirty || form.logo !== null || form.remove_logo);

const typeLabel = computed(() =>
  form.target_type === 'short_link' ? 'Short link' : (props.payloadTypes[form.payload_type] ?? form.payload_type),
);

const description = computed(() => {
  if (props.qr.is_direct) return `${typeLabel.value} · Scans not tracked`;
  return `${typeLabel.value} · ${props.qr.scans.toLocaleString()} scan${props.qr.scans === 1 ? '' : 's'}`;
});

const selectedShortLink = computed(() =>
  availableShortLinks.value.find((link) => link.id === Number(form.short_link_id)),
);

const logoLabel = computed(() => {
  if (form.logo) return form.logo.name;
  return props.qr.has_logo && !form.remove_logo ? 'Replace logo' : 'Upload logo';
});

const hasLogo = computed(() => (props.qr.has_logo && !form.remove_logo) || form.logo !== null);

function setPayloadType(type: string | number | null) {
  form.target_type = 'direct';
  form.payload_type = String(type ?? 'url');
  form.payload = payloadDefaults(form.payload_type, props.payloadDescriptors);
}

function download(format: 'png' | 'svg') {
  window.location.href = `${route('qr-codes.export', [props.qr.token, format])}?size=${exportSize.value}`;
}

function clearLogoInput() {
  if (logoInput.value) logoInput.value.value = '';
}

function save() {
  if (!props.canEditWorkspace || form.processing) return;

  form
    .transform((data) => ({
      ...(data.target_type === 'short_link'
        ? { name: data.name, short_link_id: data.short_link_id }
        : { name: data.name, short_link_id: null, payload_type: data.payload_type, payload: data.payload }),
      size: data.size,
      foreground_color: data.foreground_color,
      background_color: data.background_color,
      margin: data.margin,
      error_correction: data.error_correction,
      style: data.style,
      eye_style: data.eye_style,
      logo: data.logo,
      _method: 'patch',
      background_transparent: data.background_transparent ? 1 : 0,
      remove_logo: data.remove_logo ? 1 : 0,
    }))
    .post(route('qr-codes.update', props.qr.token), {
      preserveScroll: true,
      onSuccess: () => {
        form.logo = null;
        form.remove_logo = false;
        clearLogoInput();
        previewVersion.value += 1;
        form.defaults({ ...form.data(), logo: null, remove_logo: false });
        toast({ title: 'QR code saved', tone: 'success' });
      },
      onError: revealErrors,
    });
}

function discard() {
  form.reset();
  form.clearErrors();
  form.logo = null;
  form.remove_logo = false;
  clearLogoInput();
}

function pickLogo(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null;
  form.logo = file;
  if (file) form.remove_logo = false;
}

function removeLogo() {
  form.logo = null;
  form.remove_logo = true;
  clearLogoInput();
}

async function destroy() {
  const confirmed = await confirmAction({
    title: `Delete “${props.qr.name}”?`,
    message: 'Exported and printed copies that point to this code will stop resolving.',
    confirmLabel: 'Delete QR code',
    destructive: true,
  });
  if (confirmed) router.delete(route('qr-codes.destroy', props.qr.token));
}

function copyPublicUrl() {
  copyToClipboard(props.qr.public_url, 'Public URL copied');
}

function onKeydown(event: KeyboardEvent) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
    event.preventDefault();
    if (isDirty.value) save();
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
  <Head :title="qr.name" />

  <AuthenticatedLayout>
    <div class="flex flex-col xl:h-full xl:flex-row">
      <div
        class="flex min-w-0 flex-1 flex-col px-4 py-6 [scrollbar-gutter:stable] sm:px-6 lg:px-8 lg:py-8 xl:overflow-y-auto xl:overscroll-contain"
      >
        <PageHeader :title="qr.name" :description="description">
          <template #eyebrow>
            <Link
              :href="route('qr-codes.index')"
              class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-faint transition-colors hover:text-foreground"
            >
              <ArrowLeft class="h-3.5 w-3.5" /> QR codes
            </Link>
          </template>
          <template #actions>
            <Menu width="w-52">
              <template #trigger>
                <Button variant="secondary" size="sm" type="button" class="w-7 px-0" aria-label="More actions">
                  <MoreHorizontal />
                </Button>
              </template>
              <MenuItem :icon="Copy" @select="copyPublicUrl">Copy public URL</MenuItem>
              <MenuItem :icon="Download" @select="download('png')">Download PNG</MenuItem>
              <MenuItem :icon="Download" @select="download('svg')">Download SVG</MenuItem>
              <template v-if="canEditWorkspace">
                <MenuSeparator />
                <MenuItem :icon="Trash2" destructive @select="destroy">Delete QR code</MenuItem>
              </template>
            </Menu>
          </template>
        </PageHeader>

        <div class="flex flex-1 items-center justify-center py-8 xl:py-10">
          <div class="w-full max-w-[380px]">
            <div class="rounded-2xl border bg-surface p-2">
              <div
                class="grid place-items-center rounded-xl p-2"
                :style="
                  form.background_transparent
                    ? {
                        backgroundImage: 'repeating-conic-gradient(rgba(128,128,128,0.18) 0% 25%, transparent 0% 50%)',
                        backgroundSize: '20px 20px',
                      }
                    : { backgroundColor: form.background_color }
                "
              >
                <img :src="previewUrl" :alt="`${qr.name} QR code preview`" class="aspect-square w-full" />
              </div>
            </div>
            <p v-if="form.logo" class="pt-2.5 text-center text-xs text-faint">Save to see the new logo.</p>

            <button
              type="button"
              class="group mt-4 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors hover:bg-elevated/60"
              @click="copyPublicUrl"
            >
              <span class="min-w-0 flex-1">
                <span class="block text-xs text-faint">{{
                  form.target_type === 'short_link' ? 'Tracked URL' : 'Public URL'
                }}</span>
                <span class="block truncate text-[13px] text-foreground">{{ displayUrl(qr.public_url) }}</span>
              </span>
              <Copy class="h-3.5 w-3.5 shrink-0 text-faint transition-colors group-hover:text-foreground" />
            </button>

            <div class="mt-3 flex items-center gap-2">
              <Select
                v-model="exportSize"
                :options="EXPORT_SIZE_OPTIONS"
                size="sm"
                aria-label="Export size"
                class="w-28"
              />
              <div class="ml-auto flex gap-1.5">
                <Button variant="secondary" size="sm" type="button" @click="download('svg')"> <Download /> SVG </Button>
                <Button size="sm" type="button" @click="download('png')"><Download /> PNG</Button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <aside class="flex flex-col border-t bg-canvas xl:h-full xl:w-[420px] xl:shrink-0 xl:border-l xl:border-t-0">
        <div class="px-5 pb-4 pt-5">
          <SegmentedControl v-model="panel" :options="PANELS" label="Settings section" class="w-full" />
        </div>

        <form class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-6" @submit.prevent="save">
          <fieldset :disabled="!canEditWorkspace" class="contents">
            <div v-show="panel === 'content'" class="grid gap-5">
              <Field label="Name" :error="form.errors.name">
                <Input v-model="form.name" />
              </Field>

              <div class="grid gap-1.5">
                <span class="text-[13px] font-medium text-foreground">Opens</span>
                <SegmentedControl v-model="form.target_type" :options="TARGET_OPTIONS" label="Target" class="w-full" />
              </div>

              <template v-if="form.target_type === 'short_link'">
                <ShortLinkPicker
                  v-model="form.short_link_id"
                  v-model:search="shortLinkSearch"
                  :links="availableShortLinks"
                  :error="form.errors.short_link_id"
                />
                <p v-if="selectedShortLink" class="-mt-2 truncate text-xs text-faint">
                  Scans redirect to {{ displayUrl(selectedShortLink.destination_url) }}
                </p>
              </template>

              <template v-else>
                <Field label="Type" :error="form.errors.payload_type">
                  <Select
                    :model-value="form.payload_type"
                    :options="typeOptions"
                    @update:model-value="setPayloadType"
                  />
                </Field>
                <PayloadFields
                  v-model="form.payload"
                  :type="form.payload_type"
                  :descriptors="payloadDescriptors"
                  :errors="form.errors"
                />
                <details v-if="qr.is_direct && qr.content" class="group rounded-lg bg-surface">
                  <summary class="cursor-pointer select-none px-3 py-2 text-[13px] text-muted hover:text-foreground">
                    Encoded content
                  </summary>
                  <pre
                    class="max-h-60 overflow-auto whitespace-pre-wrap break-words border-t px-3 py-2.5 font-mono text-xs text-muted"
                    >{{ qr.content }}</pre>
                </details>
              </template>

              <div
                v-if="originalWasDirect !== (form.target_type === 'direct')"
                class="flex gap-2.5 rounded-lg bg-warning/10 px-3 py-2.5 text-[13px] leading-relaxed text-warning"
              >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                <p>Codes you’ve already exported won’t update. Export and reprint this QR code after saving.</p>
              </div>
            </div>

            <div v-show="panel === 'style'" class="grid gap-5">
              <div class="grid gap-1.5">
                <span class="text-[13px] font-medium text-foreground">Modules</span>
                <SegmentedControl v-model="form.style" :options="STYLES" label="Module style" class="w-full" />
                <span v-if="form.errors.style" class="text-xs text-danger">{{ form.errors.style }}</span>
              </div>

              <div class="grid gap-1.5">
                <span class="text-[13px] font-medium text-foreground">Corners</span>
                <SegmentedControl v-model="form.eye_style" :options="EYE_STYLES" label="Eye style" class="w-full" />
                <span v-if="form.errors.eye_style" class="text-xs text-danger">{{ form.errors.eye_style }}</span>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <Field label="Foreground" :error="form.errors.foreground_color">
                  <span
                    class="flex h-8 items-center gap-2.5 rounded-lg border border-transparent bg-elevated/70 pl-1.5 pr-3 transition-[border-color,background-color] focus-within:border-accent/60 hover:bg-elevated"
                  >
                    <input
                      v-model="form.foreground_color"
                      type="color"
                      aria-label="Foreground color"
                      class="h-5 w-5 shrink-0 cursor-pointer rounded-[5px] border-0 bg-transparent p-0 outline-none [&::-moz-color-swatch]:rounded-[5px] [&::-moz-color-swatch]:border-0 [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded-[5px] [&::-webkit-color-swatch]:border-0"
                    />
                    <span class="font-mono text-[13px] text-muted">{{ form.foreground_color.toUpperCase() }}</span>
                  </span>
                </Field>
                <Field label="Background" :error="form.errors.background_color">
                  <span
                    class="flex h-8 items-center gap-2.5 rounded-lg border border-transparent bg-elevated/70 pl-1.5 pr-3 transition-[border-color,background-color,opacity] focus-within:border-accent/60 hover:bg-elevated"
                    :class="form.background_transparent ? 'opacity-50' : ''"
                  >
                    <input
                      v-model="form.background_color"
                      type="color"
                      aria-label="Background color"
                      :disabled="form.background_transparent"
                      class="h-5 w-5 shrink-0 cursor-pointer rounded-[5px] border-0 bg-transparent p-0 outline-none disabled:cursor-not-allowed [&::-moz-color-swatch]:rounded-[5px] [&::-moz-color-swatch]:border-0 [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded-[5px] [&::-webkit-color-swatch]:border-0"
                    />
                    <span class="font-mono text-[13px] text-muted">{{
                      form.background_transparent ? 'None' : form.background_color.toUpperCase()
                    }}</span>
                  </span>
                </Field>
              </div>

              <label class="flex cursor-pointer items-center justify-between gap-4">
                <span class="min-w-0">
                  <span class="block text-[13px] font-medium text-foreground">Transparent background</span>
                  <span class="mt-0.5 block text-xs text-faint">PNG and SVG exports stay see-through.</span>
                </span>
                <Switch v-model="form.background_transparent" aria-label="Transparent background" />
              </label>

              <Field
                label="Logo"
                hint="PNG, JPG or WebP up to 2 MB. Error correction is raised automatically."
                :error="form.errors.logo"
              >
                <div class="flex items-center gap-2">
                  <label
                    class="inline-flex h-8 min-w-0 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-elevated px-3 text-[13px] font-medium text-foreground transition-colors focus-within:ring-2 focus-within:ring-accent/40 hover:bg-border-strong"
                  >
                    <Upload class="h-3.5 w-3.5 shrink-0 text-muted" />
                    <span class="truncate">{{ logoLabel }}</span>
                    <input
                      ref="logoInput"
                      type="file"
                      accept="image/png,image/jpeg,image/webp"
                      class="sr-only"
                      @change="pickLogo"
                    />
                  </label>
                  <Button v-if="hasLogo" variant="ghost" type="button" @click="removeLogo"><ImageOff /> Remove</Button>
                </div>
              </Field>
            </div>

            <div v-show="panel === 'advanced'" class="grid gap-5">
              <Field label="Margin" hint="Quiet zone around the code, in modules." :error="form.errors.margin">
                <Input v-model="form.margin" type="number" min="0" max="16" />
              </Field>
              <Field
                label="Error correction"
                hint="Higher levels survive damage and logos, at the cost of density."
                :error="form.errors.error_correction"
              >
                <Select v-model="form.error_correction" :options="ERROR_CORRECTION_OPTIONS" />
              </Field>
              <Field label="Default size" hint="Export size in pixels." :error="form.errors.size">
                <Input v-model="form.size" type="number" min="128" max="4096" />
              </Field>
            </div>
          </fieldset>
        </form>

        <Transition
          enter-active-class="transition duration-200 ease-emphasized-out"
          enter-from-class="translate-y-full"
          leave-active-class="transition duration-150 ease-out"
          leave-to-class="translate-y-full"
        >
          <div v-if="canEditWorkspace && isDirty" class="flex items-center gap-2 border-t bg-overlay px-5 py-3">
            <p class="min-w-0 flex-1 truncate text-[13px]" :class="form.hasErrors ? 'text-danger' : 'text-muted'">
              {{ form.hasErrors ? 'Some fields need attention.' : 'Unsaved changes' }}
            </p>
            <Button variant="ghost" size="sm" type="button" :disabled="form.processing" @click="discard"
              >Discard</Button
            >
            <Button size="sm" type="button" :loading="form.processing" @click="save">Save</Button>
          </div>
        </Transition>
      </aside>
    </div>
  </AuthenticatedLayout>
</template>
