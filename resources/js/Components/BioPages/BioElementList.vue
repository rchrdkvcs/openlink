<script setup lang="ts">
import { ChevronDown, Eye, EyeOff, GripVertical, Link2, Plus, Share2, Trash2, Type } from '@lucide/vue';
import { computed, ref } from 'vue';

import Switch from '@/Components/ui/Switch.vue';
import type { BioElement, BioElementType, BioShortLink } from '@/Pages/BioPages/types';
import { newBioElement } from '@/Pages/BioPages/types';

const props = defineProps<{
  elements: BioElement[];
  shortLinks: BioShortLink[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'update:elements': [elements: BioElement[]];
}>();

const expandedId = ref<string | null>(props.elements[0]?.clientId ?? null);
const draggingId = ref<string | null>(null);
const grabbedId = ref<string | null>(null);
const announcement = ref('');
const addMenuOpen = ref(false);

const canAdd = computed(() => props.elements.length < 50 && !props.disabled);

const typeLabels: Record<BioElementType, string> = {
  destination: 'Destination',
  social: 'Social destination',
  heading: 'Section heading',
  text: 'Short text',
};

function replaceElement(index: number, changes: Partial<BioElement>) {
  const elements = props.elements.map((element, elementIndex) =>
    elementIndex === index ? { ...element, ...changes } : element,
  );
  emit('update:elements', elements);
}

function addElement(type: BioElementType) {
  if (!canAdd.value) return;

  const element = newBioElement(type);
  emit('update:elements', [...props.elements, element]);
  expandedId.value = element.clientId;
  addMenuOpen.value = false;
}

function removeElement(index: number) {
  const element = props.elements[index];
  if (!element || !confirm(`Remove this ${typeLabels[element.type].toLowerCase()}?`)) return;

  emit(
    'update:elements',
    props.elements.filter((_, elementIndex) => elementIndex !== index),
  );
}

function move(from: number, to: number) {
  if (from === to || from < 0 || to < 0 || to >= props.elements.length) return;

  const elements = [...props.elements];
  const [element] = elements.splice(from, 1);
  elements.splice(to, 0, element);
  emit('update:elements', elements);
  announcement.value = `${element.label || typeLabels[element.type]} moved to position ${to + 1} of ${elements.length}.`;
}

function onDrop(index: number) {
  const from = props.elements.findIndex((element) => element.clientId === draggingId.value);
  move(from, index);
  draggingId.value = null;
}

function onHandleKeydown(event: KeyboardEvent, index: number) {
  if (props.disabled) return;

  const element = props.elements[index];

  if (event.key === ' ') {
    event.preventDefault();
    if (grabbedId.value === element.clientId) {
      grabbedId.value = null;
      announcement.value = `${element.label || typeLabels[element.type]} dropped at position ${index + 1}.`;
    } else {
      grabbedId.value = element.clientId;
      announcement.value = `${element.label || typeLabels[element.type]} grabbed. Use arrow keys to move, Space to drop, or Escape to cancel.`;
    }
    return;
  }

  if (event.key === 'Escape' && grabbedId.value) {
    event.preventDefault();
    grabbedId.value = null;
    announcement.value = 'Reordering cancelled.';
    return;
  }

  if (grabbedId.value !== element.clientId) return;

  if (event.key === 'ArrowUp') {
    event.preventDefault();
    move(index, Math.max(0, index - 1));
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    move(index, Math.min(props.elements.length - 1, index + 1));
  }
}

function detectSocialService(value: string): string {
  const host = value.toLowerCase();
  if (host.includes('instagram.com')) return 'instagram';
  if (host.includes('tiktok.com')) return 'tiktok';
  if (host.includes('youtube.com') || host.includes('youtu.be')) return 'youtube';
  if (host.includes('linkedin.com')) return 'linkedin';
  if (host.includes('github.com')) return 'github';
  if (host.includes('twitch.tv')) return 'twitch';
  if (host.includes('facebook.com')) return 'facebook';
  if (host.includes('twitter.com') || host.includes('x.com')) return 'x';
  return 'website';
}

function updateUrl(index: number, url: string) {
  const element = props.elements[index];
  replaceElement(index, {
    url,
    ...(element.type === 'social' && element.sourceType === 'external'
      ? { socialService: detectSocialService(url) }
      : {}),
  });
}
</script>

<template>
  <section aria-labelledby="bio-elements-heading">
    <div class="mb-3 flex items-center justify-between gap-3">
      <div>
        <h2 id="bio-elements-heading" class="text-sm font-semibold">Content</h2>
        <p class="mt-0.5 text-xs text-faint">{{ elements.length }} of 50 elements</p>
      </div>
      <div class="relative">
        <button
          type="button"
          class="inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-[13px] font-medium transition hover:border-border-strong disabled:opacity-50"
          :disabled="!canAdd"
          @click="addMenuOpen = !addMenuOpen"
        >
          <Plus class="h-3.5 w-3.5" /> Add element
        </button>
        <button v-if="addMenuOpen" class="fixed inset-0 z-20" tabindex="-1" @click="addMenuOpen = false" />
        <div
          v-if="addMenuOpen"
          class="absolute right-0 top-full z-30 mt-1 w-52 rounded-lg bg-overlay p-1 shadow-popover"
        >
          <button
            v-for="option in [
              { type: 'destination', label: 'Destination', icon: Link2 },
              { type: 'social', label: 'Social destination', icon: Share2 },
              { type: 'heading', label: 'Section heading', icon: Type },
              { type: 'text', label: 'Short text', icon: Type },
            ]"
            :key="option.type"
            type="button"
            class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-[13px] text-muted hover:bg-elevated hover:text-foreground"
            @click="addElement(option.type as BioElementType)"
          >
            <component :is="option.icon" class="h-4 w-4" /> {{ option.label }}
          </button>
        </div>
      </div>
    </div>

    <p aria-live="assertive" class="sr-only">{{ announcement }}</p>

    <div v-if="elements.length" class="space-y-2">
      <article
        v-for="(element, index) in elements"
        :key="element.clientId"
        class="overflow-hidden rounded-lg border bg-surface transition"
        :class="[
          draggingId === element.clientId ? 'opacity-50' : '',
          grabbedId === element.clientId ? 'ring-2 ring-accent/60' : '',
        ]"
        @dragover.prevent
        @drop.prevent="onDrop(index)"
      >
        <header class="flex min-h-11 items-center gap-1 px-2">
          <button
            type="button"
            class="grid h-8 w-8 cursor-grab place-items-center rounded-md text-faint hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 active:cursor-grabbing"
            :draggable="!disabled"
            :aria-label="`Reorder ${element.label || typeLabels[element.type]}, position ${index + 1} of ${elements.length}`"
            :aria-pressed="grabbedId === element.clientId"
            :disabled="disabled"
            @dragstart="draggingId = element.clientId"
            @dragend="draggingId = null"
            @keydown="onHandleKeydown($event, index)"
          >
            <GripVertical class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-2 px-1 py-2 text-left"
            :aria-expanded="expandedId === element.clientId"
            @click="expandedId = expandedId === element.clientId ? null : element.clientId"
          >
            <span class="min-w-0 flex-1">
              <span class="block truncate text-[13px] font-medium">{{
                element.label || element.text || typeLabels[element.type]
              }}</span>
              <span class="block text-[11px] text-faint">{{ typeLabels[element.type] }}</span>
            </span>
            <Eye v-if="element.visible" class="h-3.5 w-3.5 text-faint" />
            <EyeOff v-else class="h-3.5 w-3.5 text-faint" />
            <ChevronDown
              class="h-4 w-4 text-faint transition-transform"
              :class="expandedId === element.clientId ? 'rotate-180' : ''"
            />
          </button>
        </header>

        <div v-if="expandedId === element.clientId" class="grid gap-3 border-t p-3">
          <template v-if="element.type === 'heading' || element.type === 'text'">
            <label class="grid gap-1.5 text-[13px] font-medium">
              {{ element.type === 'heading' ? 'Heading' : 'Text' }}
              <textarea
                :value="element.text"
                :maxlength="element.type === 'heading' ? 80 : 300"
                :rows="element.type === 'heading' ? 2 : 4"
                :disabled="disabled"
                @input="replaceElement(index, { text: ($event.target as HTMLTextAreaElement).value })"
              />
              <span class="text-right text-[11px] font-normal text-faint">
                {{ element.text.length }}/{{ element.type === 'heading' ? 80 : 300 }}
              </span>
            </label>
          </template>

          <template v-else>
            <label class="grid gap-1.5 text-[13px] font-medium">
              Label
              <input
                :value="element.label"
                maxlength="80"
                :disabled="disabled"
                @input="replaceElement(index, { label: ($event.target as HTMLInputElement).value })"
              />
            </label>

            <label class="grid gap-1.5 text-[13px] font-medium">
              Destination source
              <select
                :value="element.sourceType"
                :disabled="disabled"
                @change="
                  replaceElement(index, {
                    sourceType: ($event.target as HTMLSelectElement).value as BioElement['sourceType'],
                  })
                "
              >
                <option value="external">External URL</option>
                <option value="short_link">Short Link</option>
                <option value="email">Email</option>
                <option value="telephone">Phone</option>
              </select>
            </label>

            <label v-if="element.sourceType === 'short_link'" class="grid gap-1.5 text-[13px] font-medium">
              Short Link
              <select
                :value="element.shortLinkId ?? ''"
                :disabled="disabled"
                @change="
                  replaceElement(index, { shortLinkId: Number(($event.target as HTMLSelectElement).value) || null })
                "
              >
                <option value="">Select a Short Link…</option>
                <option v-for="shortLink in shortLinks" :key="shortLink.id" :value="shortLink.id">
                  {{ shortLink.shortUrl }} · {{ shortLink.status }}
                </option>
              </select>
            </label>

            <label v-else class="grid gap-1.5 text-[13px] font-medium">
              {{
                element.sourceType === 'email'
                  ? 'Email address'
                  : element.sourceType === 'telephone'
                    ? 'Phone number'
                    : 'URL'
              }}
              <input
                :type="element.sourceType === 'email' ? 'email' : element.sourceType === 'telephone' ? 'tel' : 'url'"
                :value="element.url"
                :placeholder="
                  element.sourceType === 'email'
                    ? 'hello@example.com'
                    : element.sourceType === 'telephone'
                      ? '+33 1 23 45 67 89'
                      : 'https://example.com'
                "
                :disabled="disabled"
                @input="updateUrl(index, ($event.target as HTMLInputElement).value)"
              />
            </label>

            <div v-if="element.type === 'social'" class="grid grid-cols-2 gap-3">
              <label class="grid gap-1.5 text-[13px] font-medium">
                Service
                <select
                  :value="element.socialService"
                  :disabled="disabled"
                  @change="replaceElement(index, { socialService: ($event.target as HTMLSelectElement).value })"
                >
                  <option
                    v-for="service in [
                      'website',
                      'instagram',
                      'tiktok',
                      'x',
                      'youtube',
                      'linkedin',
                      'github',
                      'twitch',
                      'facebook',
                    ]"
                    :key="service"
                    :value="service"
                  >
                    {{ service === 'x' ? 'X' : service.charAt(0).toUpperCase() + service.slice(1) }}
                  </option>
                </select>
              </label>
              <label class="grid gap-1.5 text-[13px] font-medium">
                Presentation
                <select
                  :value="element.presentation"
                  :disabled="disabled"
                  @change="
                    replaceElement(index, {
                      presentation: ($event.target as HTMLSelectElement).value as BioElement['presentation'],
                    })
                  "
                >
                  <option value="icon">Compact icon</option>
                  <option value="button">Destination button</option>
                </select>
              </label>
            </div>

            <label class="flex items-center justify-between gap-4 text-[13px]">
              <span>
                <span class="block font-medium">Open in a new tab</span>
                <span class="block text-xs text-faint">Announced to visitors using assistive technology.</span>
              </span>
              <Switch
                :model-value="element.openInNewTab"
                @update:model-value="replaceElement(index, { openInNewTab: $event })"
              />
            </label>
          </template>

          <div class="flex items-center justify-between border-t pt-3">
            <label class="flex items-center gap-2 text-[13px] font-medium">
              <Switch :model-value="element.visible" @update:model-value="replaceElement(index, { visible: $event })" />
              Visible
            </label>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-danger hover:bg-danger/10"
              :disabled="disabled"
              @click="removeElement(index)"
            >
              <Trash2 class="h-3.5 w-3.5" /> Remove
            </button>
          </div>
        </div>
      </article>
    </div>

    <button
      v-else-if="canAdd"
      type="button"
      class="w-full rounded-lg border border-dashed p-8 text-center text-sm text-muted transition hover:border-border-strong hover:bg-elevated/30 hover:text-foreground"
      @click="addMenuOpen = true"
    >
      Add the first destination to this Bio Page
    </button>
  </section>
</template>
