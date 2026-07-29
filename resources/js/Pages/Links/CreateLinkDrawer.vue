<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import {
  ArrowRight,
  CalendarClock,
  CalendarOff,
  Folder as FolderIcon,
  Gauge,
  LifeBuoy,
  Link2,
  Lock,
  Route,
  Tags as TagsIcon,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import DateTimeField from '@/Components/ui/DateTimeField.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import TagInput from '@/Components/ui/TagInput.vue';
import { isLikelyUrl } from '@/lib/links';

import DestinationUrlField from './DestinationUrlField.vue';
import OptionChips from './OptionChips.vue';
import OptionRow from './OptionRow.vue';
import RoutingRulesEditor from './RoutingRulesEditor.vue';
import ShortUrlComposer from './ShortUrlComposer.vue';
import type { CreateLinkFormData, Domain, Folder, RoutingSchema } from './types';

const props = defineProps<{
  show: boolean;
  form: InertiaForm<CreateLinkFormData>;
  domains: Domain[];
  folders: Folder[];
  knownTags: { id: number; name: string }[];
  routingSchema: RoutingSchema;
}>();

const emit = defineEmits<{ close: []; submit: [] }>();

const tab = ref<'link' | 'routing'>('link');
const destinationValid = computed(() => isLikelyUrl(props.form.destination_url));

// ── Short URL segment control ────────────────────────────────────────────────
const selectedDomain = computed(() => props.domains.find((d) => d.id === Number(props.form.domain_id)));
const previewSlug = computed(() => props.form.slug.trim() || 'auto');

// ── Progressive options ──────────────────────────────────────────────────────
type OptionKey = 'folder_id' | 'activates_at' | 'expires_at' | 'visit_limit' | 'password' | 'fallback_url' | 'tags';

const OPTIONS: { key: OptionKey; label: string; icon: unknown }[] = [
  { key: 'activates_at', label: 'Activation', icon: CalendarClock },
  { key: 'expires_at', label: 'Expiration', icon: CalendarOff },
  { key: 'visit_limit', label: 'Visit limit', icon: Gauge },
  { key: 'password', label: 'Password', icon: Lock },
  { key: 'folder_id', label: 'Folder', icon: FolderIcon },
  { key: 'fallback_url', label: 'Fallback URL', icon: LifeBuoy },
  { key: 'tags', label: 'Tags', icon: TagsIcon },
];

const activeOptions = ref<OptionKey[]>([]);
const availableOptions = computed(() => OPTIONS.filter((o) => !activeOptions.value.includes(o.key)));
const optionDef = (key: OptionKey) => OPTIONS.find((o) => o.key === key)!;

function addOption(key: OptionKey) {
  activeOptions.value = [...activeOptions.value, key];
}

function removeOption(key: OptionKey) {
  activeOptions.value = activeOptions.value.filter((k) => k !== key);
  props.form[key] = '';
}

// Fresh drawer on reopen: options collapse back to chips once the form is clean.
watch(
  () => props.show,
  (show) => {
    if (show && !props.form.isDirty) {
      activeOptions.value = [];
      tab.value = 'link';
    }
  },
);
</script>

<template>
  <Drawer :show="show" @close="emit('close')">
    <template #header>
      <div class="flex min-w-0 items-center gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-accent/30 bg-accent/10">
          <Link2 class="h-4 w-4 text-accent" />
        </span>
        <div class="min-w-0">
          <h3 class="text-[15px] font-semibold text-foreground">New link</h3>
          <p class="truncate text-xs text-faint">
            Paste a destination, shape the short URL, add options as you need them.
          </p>
        </div>
      </div>
    </template>

    <form class="flex min-h-full flex-col" @submit.prevent="emit('submit')">
      <div class="flex-1 space-y-5 p-5">
        <div class="grid grid-cols-2 rounded-lg border bg-elevated/30 p-1">
          <button
            v-for="entry in [
              { key: 'link' as const, label: 'Link', icon: Link2 },
              { key: 'routing' as const, label: 'Routing', icon: Route },
            ]"
            :key="entry.key"
            type="button"
            class="flex h-8 items-center justify-center gap-1.5 rounded-md text-[13px] font-medium transition-colors"
            :class="tab === entry.key ? 'bg-surface text-foreground shadow-sm' : 'text-muted hover:text-foreground'"
            @click="tab = entry.key"
          >
            <component :is="entry.icon" class="h-3.5 w-3.5" />
            {{ entry.label }}
          </button>
        </div>

        <div v-if="tab === 'link'" class="space-y-6">
          <!-- Destination hero -->
          <DestinationUrlField v-model="form.destination_url" :error="form.errors.destination_url" autofocus />

          <!-- Short URL segment control -->
          <div>
            <p class="mb-1.5 text-[13px] font-medium text-foreground">Short URL</p>
            <ShortUrlComposer v-model:domain-id="form.domain_id" v-model:slug="form.slug" :domains="domains" />
            <p v-if="form.errors.slug || form.errors.domain_id" class="mt-1.5 text-xs text-danger">
              {{ form.errors.slug ?? form.errors.domain_id }}
            </p>
            <p v-else class="mt-1.5 text-xs text-faint">Leave the slug empty to generate one automatically.</p>
          </div>

          <!-- Active option rows -->
          <TransitionGroup
            v-if="activeOptions.length"
            tag="div"
            class="space-y-3"
            enter-active-class="animate-slide-up"
          >
            <OptionRow
              v-for="key in activeOptions"
              :key="key"
              :label="optionDef(key).label"
              :icon="optionDef(key).icon"
              @remove="removeOption(key)"
            >
              <template v-if="key === 'activates_at' || key === 'expires_at'">
                <DateTimeField v-model="form[key]" />
                <p v-if="form.errors[key]" class="mt-1.5 text-xs text-danger">{{ form.errors[key] }}</p>
              </template>

              <template v-else-if="key === 'visit_limit'">
                <StepperInput v-model="form.visit_limit" :step="100" placeholder="Unlimited" />
                <p class="mt-1.5 text-xs" :class="form.errors.visit_limit ? 'text-danger' : 'text-faint'">
                  {{ form.errors.visit_limit ?? 'The link stops resolving after this many visits.' }}
                </p>
              </template>

              <template v-else-if="key === 'password'">
                <PasswordInput v-model="form.password" placeholder="Visitors must enter this to continue" />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-danger">
                  {{ form.errors.password }}
                </p>
              </template>

              <template v-else-if="key === 'folder_id'">
                <select v-model="form.folder_id" class="h-9">
                  <option value="">No folder</option>
                  <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                    {{ folder.name }}
                  </option>
                </select>
                <p v-if="form.errors.folder_id" class="mt-1.5 text-xs text-danger">
                  {{ form.errors.folder_id }}
                </p>
              </template>

              <template v-else-if="key === 'fallback_url'">
                <input v-model="form.fallback_url" class="h-9" placeholder="https://fallback.example" />
                <p class="mt-1.5 text-xs" :class="form.errors.fallback_url ? 'text-danger' : 'text-faint'">
                  {{ form.errors.fallback_url ?? 'Shown when the link is expired or unavailable.' }}
                </p>
              </template>

              <template v-else-if="key === 'tags'">
                <TagInput v-model="form.tags" :suggestions="knownTags" />
                <p v-if="form.errors.tags" class="mt-1.5 text-xs text-danger">{{ form.errors.tags }}</p>
              </template>
            </OptionRow>
          </TransitionGroup>

          <OptionChips :options="availableOptions" @add="addOption" />
        </div>

        <RoutingRulesEditor v-else v-model="form.routing_rules" :errors="form.errors" :schema="routingSchema" />
      </div>

      <!-- Sticky footer with live preview -->
      <footer class="sticky bottom-0 flex shrink-0 items-center justify-between gap-3 border-t bg-overlay px-5 py-4">
        <p class="min-w-0 truncate font-mono text-[13px] text-faint">
          <span class="text-muted">{{ selectedDomain?.hostname ?? '—' }}</span
          >/<span :class="form.slug ? 'text-accent' : 'italic'">{{ previewSlug }}</span>
        </p>
        <div class="flex shrink-0 gap-2">
          <Button variant="secondary" type="button" @click="emit('close')">Cancel</Button>
          <Button :loading="form.processing" :disabled="!destinationValid">
            Create link
            <ArrowRight class="h-3.5 w-3.5" />
          </Button>
        </div>
      </footer>
    </form>
  </Drawer>
</template>
