<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Download, ImageOff, LinkIcon, Trash2, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import Select from '@/Components/ui/Select.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { SelectOption } from '@/lib/controls';

import PayloadFields from './PayloadFields.vue';
import type { PayloadDescriptors, QrCodeRecord, ShortLinkOption } from './types';
import { payloadDefaults } from './types';
import { useShortLinkSearch } from './useShortLinkSearch';

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

const EXPORT_SIZES = [512, 1024, 2048, 4096];
const EXPORT_SIZE_OPTIONS: SelectOption<number>[] = EXPORT_SIZES.map((size) => ({ value: size, label: `${size} px` }));

const TARGET_OPTIONS: SelectOption[] = [
  { value: 'short_link', label: 'Short Link' },
  { value: 'direct', label: 'Direct payload' },
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
  target_type: props.qr.is_direct ? 'direct' : 'short_link',
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

const {
  search: shortLinkSearch,
  links: availableShortLinks,
  options: shortLinkOptions,
} = useShortLinkSearch(
  props.shortLinks,
  computed(() => form.short_link_id),
);
const copied = ref(false);
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

const isDirty = computed(() => form.isDirty || form.logo !== null || form.remove_logo);

function setPayloadType(type: string) {
  form.target_type = 'direct';
  form.payload_type = type;
  form.payload = payloadDefaults(type, props.payloadDescriptors);
}

function exportUrl(format: 'png' | 'svg') {
  return `${route('qr-codes.export', [props.qr.token, format])}?size=${exportSize.value}`;
}

function save() {
  if (!props.canEditWorkspace) {
    return;
  }

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
        if (logoInput.value) {
          logoInput.value.value = '';
        }
        previewVersion.value += 1;
        form.defaults({ ...form.data(), logo: null, remove_logo: false });
      },
    });
}

function pickLogo(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null;
  form.logo = file;
  if (file) {
    form.remove_logo = false;
  }
}

function removeLogo() {
  form.logo = null;
  form.remove_logo = true;
  if (logoInput.value) {
    logoInput.value.value = '';
  }
}

function destroy() {
  if (confirm(`Delete the QR Code “${props.qr.name}”? Exported images will stop resolving.`)) {
    router.delete(route('qr-codes.destroy', props.qr.token));
  }
}

async function copyPublicUrl() {
  try {
    await navigator.clipboard.writeText(props.qr.public_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  } catch {
    // Clipboard unavailable in insecure contexts.
  }
}
</script>

<template>
  <Head :title="`QR Code — ${qr.name}`" />

  <AuthenticatedLayout>
    <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
      <div class="mb-6">
        <Link
          :href="route('qr-codes.index')"
          class="inline-flex items-center gap-1.5 text-[13px] font-medium text-muted transition-colors hover:text-foreground"
        >
          <ArrowLeft class="h-3.5 w-3.5" /> Back to QR Codes
        </Link>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <h1 class="text-xl font-semibold tracking-tight">QR Code — {{ qr.name }}</h1>
          <Badge>{{
            form.target_type === 'short_link' ? 'Short Link' : (payloadTypes[form.payload_type] ?? form.payload_type)
          }}</Badge>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
        <div class="grid content-start gap-6">
          <SectionCard>
            <div class="grid grid-cols-1 gap-5 p-5">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-foreground">
                    {{ form.target_type === 'short_link' ? 'Tracked QR URL' : 'Public payload URL' }}
                  </p>
                  <p class="mt-0.5 truncate font-mono text-xs text-faint">{{ qr.public_url }}</p>
                </div>
                <LinkIcon class="h-4 w-4 shrink-0 text-faint" />
              </div>

              <div
                class="mx-auto w-full max-w-md rounded-xl border p-6"
                :style="
                  form.background_transparent
                    ? {
                        backgroundImage: 'repeating-conic-gradient(rgba(128,128,128,0.18) 0% 25%, transparent 0% 50%)',
                        backgroundSize: '20px 20px',
                      }
                    : { backgroundColor: form.background_color }
                "
              >
                <img :src="previewUrl" :alt="`${qr.name} QR code preview`" class="mx-auto aspect-square w-full" />
              </div>
              <p v-if="form.logo" class="text-center text-xs text-faint">
                Save to see the uploaded logo in the preview.
              </p>

              <div class="flex items-center gap-2 rounded-md border bg-elevated/40 px-3 py-2">
                <p class="min-w-0 flex-1 truncate font-mono text-xs text-muted">{{ qr.public_url }}</p>
                <button
                  type="button"
                  class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md border px-2 text-xs font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                  @click="copyPublicUrl"
                >
                  <CopyCheckIcon :copied="copied" />
                  {{ copied ? 'Copied' : 'Copy' }}
                </button>
              </div>

              <div class="flex flex-wrap items-center justify-center gap-2 border-t pt-4">
                <Select v-model="exportSize" :options="EXPORT_SIZE_OPTIONS" class="w-28" />
                <a
                  :href="exportUrl('png')"
                  class="inline-flex h-9 items-center gap-1.5 rounded-md bg-foreground px-3.5 text-sm font-medium text-background transition-colors hover:bg-foreground/85"
                >
                  <Download class="h-4 w-4" /> PNG
                </a>
                <a
                  :href="exportUrl('svg')"
                  class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3.5 text-sm font-medium text-foreground transition-colors hover:border-border-strong hover:bg-elevated"
                >
                  <Download class="h-4 w-4" /> SVG
                </a>
              </div>
            </div>
          </SectionCard>

          <SectionCard :title="form.target_type === 'short_link' ? 'Linked Short Link' : 'Encoded payload'">
            <div v-if="form.target_type === 'short_link'" class="p-5 text-sm">
              <p class="font-medium text-foreground">
                {{
                  availableShortLinks.find((link) => link.id === Number(form.short_link_id))?.short_url ??
                  'Select a Short Link'
                }}
              </p>
              <p class="mt-1 truncate text-xs text-faint">
                {{ availableShortLinks.find((link) => link.id === Number(form.short_link_id))?.destination_url }}
              </p>
            </div>
            <pre
              v-else
              class="max-h-80 overflow-auto whitespace-pre-wrap break-words p-5 font-mono text-xs text-muted"
              >{{ qr.content }}</pre>
          </SectionCard>
        </div>

        <SectionCard>
          <form class="grid grid-cols-1 gap-5 p-5" @submit.prevent="save">
            <Field label="Name" :error="form.errors.name">
              <Input v-model="form.name" />
            </Field>

            <Field label="Target" :error="form.errors.short_link_id">
              <Select v-model="form.target_type" :options="TARGET_OPTIONS" />
            </Field>

            <Field v-if="form.target_type === 'short_link'" label="Short Link" :error="form.errors.short_link_id">
              <Input v-model="shortLinkSearch" class="mb-2" placeholder="Search Short Links…" />
              <Select v-model="form.short_link_id" :options="shortLinkOptions" placeholder="Select a Short Link…" />
            </Field>

            <div
              v-if="originalWasDirect !== (form.target_type === 'direct')"
              class="flex gap-2 rounded-md border border-warning/40 bg-warning/10 p-3 text-xs text-warning"
            >
              <AlertTriangle class="h-4 w-4 shrink-0" />
              <p>
                Changing to or from a direct payload cannot update previously exported native QR images. Export and
                reprint the QR Code after saving.
              </p>
            </div>

            <Field v-if="form.target_type === 'direct'" label="Type" :error="form.errors.payload_type">
              <Select :model-value="form.payload_type" :options="typeOptions" @update:model-value="setPayloadType" />
            </Field>

            <PayloadFields
              v-if="form.target_type === 'direct'"
              v-model="form.payload"
              :type="form.payload_type"
              :descriptors="payloadDescriptors"
              :errors="form.errors"
            />

            <div class="border-t pt-5">
              <p class="mb-3 text-[13px] font-medium text-foreground">Appearance</p>

              <div class="grid gap-5">
                <Field label="Module style" :error="form.errors.style">
                  <div class="grid grid-cols-3 gap-2">
                    <button
                      v-for="style in STYLES"
                      :key="style.value"
                      type="button"
                      class="h-9 rounded-md border text-[13px] font-medium transition-colors"
                      :class="
                        form.style === style.value
                          ? 'border-foreground bg-elevated text-foreground'
                          : 'text-muted hover:border-border-strong hover:text-foreground'
                      "
                      @click="form.style = style.value"
                    >
                      {{ style.label }}
                    </button>
                  </div>
                </Field>

                <Field label="Eye style" :error="form.errors.eye_style">
                  <div class="grid grid-cols-3 gap-2">
                    <button
                      v-for="eye in EYE_STYLES"
                      :key="eye.value"
                      type="button"
                      class="h-9 rounded-md border text-[13px] font-medium transition-colors"
                      :class="
                        form.eye_style === eye.value
                          ? 'border-foreground bg-elevated text-foreground'
                          : 'text-muted hover:border-border-strong hover:text-foreground'
                      "
                      @click="form.eye_style = eye.value"
                    >
                      {{ eye.label }}
                    </button>
                  </div>
                </Field>

                <div class="grid gap-4 sm:grid-cols-2">
                  <Field label="Foreground" :error="form.errors.foreground_color">
                    <input
                      v-model="form.foreground_color"
                      type="color"
                      class="h-9 w-full cursor-pointer rounded-md border bg-surface p-1"
                    />
                  </Field>
                  <Field label="Background" :error="form.errors.background_color">
                    <input
                      v-model="form.background_color"
                      type="color"
                      class="h-9 w-full cursor-pointer rounded-md border bg-surface p-1"
                      :disabled="form.background_transparent"
                    />
                  </Field>
                </div>

                <label class="flex items-center justify-between gap-3 rounded-md border bg-elevated/40 px-3 py-2.5">
                  <span>
                    <span class="block text-[13px] font-medium text-foreground">Transparent background</span>
                    <span class="block text-xs text-faint">PNG and SVG exports keep the background see-through.</span>
                  </span>
                  <Checkbox v-model="form.background_transparent" />
                </label>

                <Field
                  label="Logo"
                  hint="PNG, JPG or WebP, 2 MB max. Error correction is raised automatically."
                  :error="form.errors.logo"
                >
                  <div class="flex items-center gap-2">
                    <label
                      class="inline-flex h-9 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md border text-[13px] font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                    >
                      <Upload class="h-3.5 w-3.5" />
                      {{
                        form.logo ? form.logo.name : qr.has_logo && !form.remove_logo ? 'Replace logo' : 'Upload logo'
                      }}
                      <input
                        ref="logoInput"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="hidden"
                        @change="pickLogo"
                      />
                    </label>
                    <button
                      v-if="(qr.has_logo && !form.remove_logo) || form.logo"
                      type="button"
                      class="inline-flex h-9 items-center gap-1.5 rounded-md border px-2.5 text-[13px] font-medium text-muted transition-colors hover:border-danger/50 hover:text-danger"
                      @click="removeLogo"
                    >
                      <ImageOff class="h-3.5 w-3.5" /> Remove
                    </button>
                  </div>
                </Field>

                <div class="grid gap-4 sm:grid-cols-3">
                  <Field label="Margin" hint="Quiet zone." :error="form.errors.margin">
                    <Input v-model="form.margin" type="number" min="0" max="16" />
                  </Field>
                  <Field label="Error correction" :error="form.errors.error_correction">
                    <Select v-model="form.error_correction" :options="ERROR_CORRECTION_OPTIONS" />
                  </Field>
                  <Field label="Default size" :error="form.errors.size">
                    <Input v-model="form.size" type="number" min="128" max="4096" />
                  </Field>
                </div>
              </div>
            </div>

            <div v-if="canEditWorkspace" class="flex items-center justify-between border-t pt-4">
              <Button variant="danger" type="button" size="sm" @click="destroy">
                <Trash2 class="h-3.5 w-3.5" /> Delete
              </Button>
              <Button :loading="form.processing" :disabled="!isDirty">Save changes</Button>
            </div>
          </form>
        </SectionCard>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
