<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Copy, ExternalLink, ImagePlus, Trash2 } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';

import LinkPageContent from '@/Components/LinkPages/LinkPageContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SaveBar from '@/Components/ui/SaveBar.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmAction } from '@/lib/confirm';
import { useShell } from '@/lib/shell';
import { copyToClipboard, toast } from '@/lib/toast';

import type { ShortLinkOption } from '../QrCodes/types';
import LinkPageItemRow from './LinkPageItemRow.vue';
import {
  addItem,
  MAX_ITEMS,
  moveItem,
  normalizePath,
  previewPage,
  removeItem,
  toEditable,
  toItemsPayload,
} from './linkPageItems';
import LinkPicker from './LinkPicker.vue';
import type { LinkPageRecord } from './types';

const props = defineProps<{
  linkPage: LinkPageRecord;
  shortLinks: ShortLinkOption[];
  pathPrefix: string;
}>();

const { canEdit } = useShell();

function fields(linkPage: LinkPageRecord) {
  return {
    name: linkPage.name,
    path: linkPage.path,
    title: linkPage.title,
    description: linkPage.description ?? '',
    items: toEditable(linkPage.items),
    logo: null as File | null,
    remove_logo: false,
  };
}

const form = useForm(fields(props.linkPage));
const logoInput = ref<HTMLInputElement | null>(null);
const logoObjectUrl = ref<string | null>(null);

const isDirty = computed(() => form.isDirty || form.logo !== null || form.remove_logo);
const selectedIds = computed(() => form.items.map((item) => item.short_link_id));
const logo = computed(() => {
  if (logoObjectUrl.value) return logoObjectUrl.value;
  return form.remove_logo ? null : props.linkPage.logo;
});
const preview = computed(() => previewPage(form, logo.value, form.items));
const status = computed(() => (props.linkPage.is_published ? 'Published' : 'Draft'));

watch(
  () => form.logo,
  (file) => {
    if (logoObjectUrl.value) URL.revokeObjectURL(logoObjectUrl.value);
    logoObjectUrl.value = file ? URL.createObjectURL(file) : null;
  },
);
onUnmounted(() => {
  if (logoObjectUrl.value) URL.revokeObjectURL(logoObjectUrl.value);
});

function itemError(index: number) {
  const errors = form.errors as Record<string, string | undefined>;
  return errors[`items.${index}.short_link_id`] ?? errors[`items.${index}.label`];
}

function pickLogo(event: Event) {
  const input = event.target as HTMLInputElement;
  form.logo = input.files?.[0] ?? null;
  if (form.logo) form.remove_logo = false;
  input.value = '';
}

function removeLogo() {
  form.logo = null;
  form.remove_logo = true;
}

function save() {
  if (!canEdit.value || form.processing) return;

  form
    .transform((data) => ({ ...data, items: toItemsPayload(data.items), _method: 'patch' }))
    .post(route('link-pages.update', props.linkPage.id), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        form.defaults(fields(props.linkPage));
        form.reset();
        toast({ title: 'Link page saved', tone: 'success' });
      },
    });
}

function discard() {
  form.reset();
  form.clearErrors();
}

function setPublished(published: boolean) {
  router.post(
    route(published ? 'link-pages.publish' : 'link-pages.unpublish', props.linkPage.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: published ? 'Link page published' : 'Link page unpublished', tone: 'success' }),
    },
  );
}

async function destroy() {
  const confirmed = await confirmAction({
    title: `Delete “${props.linkPage.name}”?`,
    message: 'Its public URL will stop working. The short links on it are not changed.',
    confirmLabel: 'Delete link page',
    destructive: true,
  });
  if (confirmed) router.delete(route('link-pages.destroy', props.linkPage.id));
}
</script>

<template>
  <Head :title="linkPage.name" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <PageHeader :title="linkPage.name">
        <template #eyebrow>
          <Link
            :href="route('link-pages.index')"
            class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-faint transition-colors hover:text-foreground"
          >
            <ArrowLeft class="h-3.5 w-3.5" /> Link pages
          </Link>
        </template>
        <template #actions>
          <Button
            variant="ghost"
            size="sm"
            type="button"
            @click="copyToClipboard(linkPage.public_url, 'Page URL copied')"
          >
            <Copy /> Copy URL
          </Button>
          <a
            v-if="linkPage.is_published"
            :href="linkPage.public_url"
            target="_blank"
            rel="noopener"
            class="inline-flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-medium text-muted transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 [&_svg]:h-3.5 [&_svg]:w-3.5"
          >
            <ExternalLink /> Open
          </a>
          <template v-if="canEdit">
            <Button variant="ghost" size="sm" type="button" title="Delete link page" @click="destroy">
              <Trash2 /> Delete
            </Button>
            <Button
              v-if="linkPage.is_published"
              variant="secondary"
              size="sm"
              type="button"
              :disabled="isDirty"
              @click="setPublished(false)"
            >
              Unpublish
            </Button>
            <Button v-else size="sm" type="button" :disabled="isDirty" @click="setPublished(true)">Publish</Button>
          </template>
        </template>
      </PageHeader>

      <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
        <Badge :variant="linkPage.is_published ? 'success' : 'default'" dot>{{ status }}</Badge>
        <span class="break-all">{{ linkPage.public_url }}</span>
        <span v-if="canEdit && isDirty" class="text-faint">Save changes before publishing.</span>
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,400px)]">
        <form class="grid content-start gap-6" @submit.prevent="save">
          <fieldset :disabled="!canEdit" class="contents">
            <SectionCard title="Page" description="Plain text only. Visitors never see the name or your workspace.">
              <div class="grid gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                  <Field label="Name" hint="Shown only in your dashboard." :error="form.errors.name">
                    <Input v-model="form.name" maxlength="120" />
                  </Field>
                  <Field label="Path" :hint="`${pathPrefix}${form.path}`" :error="form.errors.path">
                    <Input
                      :model-value="form.path"
                      maxlength="64"
                      spellcheck="false"
                      autocapitalize="off"
                      @update:model-value="form.path = normalizePath(String($event ?? ''))"
                    />
                  </Field>
                </div>
                <Field label="Title" :error="form.errors.title">
                  <Input v-model="form.title" maxlength="120" />
                </Field>
                <Field
                  label="Description"
                  :hint="`${form.description.length}/280 characters`"
                  :error="form.errors.description"
                >
                  <Textarea v-model="form.description" rows="3" maxlength="280" />
                </Field>
                <div class="grid gap-1.5">
                  <span class="text-[13px] font-medium text-foreground">Logo</span>
                  <div class="flex flex-wrap items-center gap-2">
                    <input
                      ref="logoInput"
                      type="file"
                      accept="image/png,image/jpeg,image/webp"
                      class="sr-only"
                      aria-label="Upload logo"
                      @change="pickLogo"
                    />
                    <Button variant="secondary" size="sm" type="button" @click="logoInput?.click()">
                      <ImagePlus /> {{ logo ? 'Replace logo' : 'Upload logo' }}
                    </Button>
                    <Button v-if="logo" variant="ghost" size="sm" type="button" @click="removeLogo">Remove</Button>
                  </div>
                  <span v-if="form.errors.logo" class="text-xs text-danger">{{ form.errors.logo }}</span>
                  <span v-else class="text-xs text-faint">PNG, JPEG, or WebP up to 2 MB. Resized to 256 px.</span>
                </div>
              </div>
            </SectionCard>

            <SectionCard
              title="Links"
              :description="`Visitors open each short URL, so passwords, schedules, and routing still apply. ${form.items.length}/${MAX_ITEMS} links.`"
            >
              <div class="grid gap-4">
                <ol v-if="form.items.length" class="grid gap-2" aria-label="Links on this page">
                  <LinkPageItemRow
                    v-for="(item, index) in form.items"
                    :key="item.short_link_id"
                    :item="item"
                    :index="index"
                    :count="form.items.length"
                    :error="itemError(index)"
                    @move="form.items = moveItem(form.items, index, $event)"
                    @remove="form.items = removeItem(form.items, index)"
                    @label="item.label = $event"
                  />
                </ol>
                <p v-else class="rounded-xl border border-dashed px-4 py-6 text-center text-[13px] text-faint">
                  Add short links to show them on this page.
                </p>
                <LinkPicker
                  v-if="canEdit"
                  :initial="shortLinks"
                  :selected-ids="selectedIds"
                  :disabled="form.items.length >= MAX_ITEMS"
                  @add="form.items = addItem(form.items, $event)"
                />
              </div>
            </SectionCard>
          </fieldset>

          <SaveBar
            v-if="canEdit"
            :dirty="isDirty"
            :processing="form.processing"
            :has-errors="form.hasErrors"
            @save="save"
            @discard="discard"
          />
        </form>

        <aside class="lg:sticky lg:top-6 lg:self-start" aria-label="Preview">
          <p class="mb-2 text-xs font-medium text-faint">Preview</p>
          <div class="max-h-[80vh] overflow-y-auto rounded-xl border bg-background px-4 py-10" inert>
            <LinkPageContent :page="preview" />
          </div>
        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
