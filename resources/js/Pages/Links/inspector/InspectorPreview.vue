<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { ImageOff, Share2, Upload } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { linkPreviewCard, type ShortLinkForm } from '@/lib/shortLinks/shortLinkForm';
import type { ShortLink } from '@/types/shortLinks';

import InspectorRow from './InspectorRow.vue';

const props = defineProps<{ form: InertiaForm<ShortLinkForm>; link: ShortLink }>();

const open = defineModel<boolean>('open', { default: false });

const input = ref<HTMLInputElement | null>(null);
const stagedImageUrl = ref<string | null>(null);

watch(
  () => props.form.preview_image,
  (file) => {
    if (stagedImageUrl.value) URL.revokeObjectURL(stagedImageUrl.value);
    stagedImageUrl.value = file ? URL.createObjectURL(file) : null;
    if (!file && input.value) input.value.value = '';
  },
);

onUnmounted(() => {
  if (stagedImageUrl.value) URL.revokeObjectURL(stagedImageUrl.value);
});

const card = computed(() => linkPreviewCard(props.link, props.form, stagedImageUrl.value));

const customized = computed(() =>
  Boolean(props.form.preview_title.trim() || props.form.preview_description.trim() || card.value.imageUrl),
);

const isPrivate = computed(
  () => props.link.has_password || props.form.password !== '' || props.link.status !== 'active',
);

const imageLabel = computed(() => {
  if (props.form.preview_image) return props.form.preview_image.name;
  return card.value.imageUrl ? 'Replace image' : 'Upload image';
});

function pick(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null;
  props.form.preview_image = file;
  if (file) props.form.remove_preview_image = false;
}

function removeImage() {
  props.form.preview_image = null;
  props.form.remove_preview_image = Boolean(props.link.preview?.image_url);
}
</script>

<template>
  <InspectorRow
    v-model:open="open"
    :icon="Share2"
    label="Social preview"
    :summary="customized ? 'Custom' : 'From destination'"
    :active="customized"
  >
    <Field label="Title" :error="form.errors.preview_title">
      <Input v-model="form.preview_title" maxlength="120" placeholder="Shown as the card headline" />
    </Field>
    <Field label="Description" :error="form.errors.preview_description">
      <Textarea v-model="form.preview_description" maxlength="300" rows="2" placeholder="A short summary of the page" />
    </Field>
    <Field
      label="Image"
      hint="PNG, JPG or WebP up to 5 MB and 4096 × 4096 px. 1200 × 630 works best."
      :error="form.errors.preview_image"
    >
      <div class="flex items-center gap-2">
        <label
          class="inline-flex h-8 min-w-0 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-elevated px-3 text-[13px] font-medium text-foreground transition-colors focus-within:ring-2 focus-within:ring-accent/40 hover:bg-border-strong"
        >
          <Upload class="h-3.5 w-3.5 shrink-0 text-muted" />
          <span class="truncate">{{ imageLabel }}</span>
          <input ref="input" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pick" />
        </label>
        <Button v-if="card.imageUrl" variant="ghost" type="button" @click="removeImage"><ImageOff /> Remove</Button>
      </div>
    </Field>

    <figure class="overflow-hidden rounded-lg border bg-canvas" aria-label="Illustrative social card">
      <div v-if="card.imageUrl" class="aspect-[1200/630] bg-elevated">
        <img :src="card.imageUrl" alt="" class="h-full w-full object-cover" />
      </div>
      <figcaption class="grid gap-0.5 px-3 py-2.5">
        <span class="truncate text-[11px] uppercase tracking-wide text-faint">{{ card.host }}</span>
        <span class="truncate text-[13px] font-semibold text-foreground">{{ card.title }}</span>
        <span v-if="card.description" class="line-clamp-2 text-xs text-muted">{{ card.description }}</span>
      </figcaption>
    </figure>

    <p class="text-xs leading-relaxed text-faint">
      Illustrative only. Each platform renders cards its own way and caches them independently, so changes can take a
      while to appear where the link was already shared.
    </p>
    <p v-if="isPrivate" class="text-xs leading-relaxed text-warning">
      This link is protected or not active, so platforms receive a neutral preview instead.
    </p>
  </InspectorRow>
</template>
