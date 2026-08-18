<script setup lang="ts">
import { Link, type InertiaForm } from '@inertiajs/vue3';
import {
  CalendarClock,
  CalendarOff,
  Folder as FolderIcon,
  Gauge,
  LifeBuoy,
  Link2,
  Lock,
  QrCode,
  Route,
  Settings2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import DateTimeField from '@/Components/ui/DateTimeField.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Field from '@/Components/ui/Field.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import Switch from '@/Components/ui/Switch.vue';

import DestinationUrlField from './DestinationUrlField.vue';
import OptionChips from './OptionChips.vue';
import OptionRow from './OptionRow.vue';
import RoutingRulesEditor from './RoutingRulesEditor.vue';
import ShortUrlComposer from './ShortUrlComposer.vue';
import type { Domain, EditLinkFormData, Folder, Qr, RoutingSchema, ShortLink } from './types';

const props = defineProps<{
  link: ShortLink | null;
  editForm: InertiaForm<EditLinkFormData>;
  qrForm: InertiaForm<{ name: string }>;
  domains: Domain[];
  folders: Folder[];
  routingSchema: RoutingSchema;
}>();

const emit = defineEmits<{ close: []; submit: []; submitQr: [] }>();

const tab = ref<'link' | 'routing' | 'qr'>('link');

// ── Progressive options — settings already on the link open expanded, the rest are chips ──
type OptionKey = 'activates_at' | 'expires_at' | 'visit_limit' | 'password' | 'folder_id' | 'fallback_url';

const OPTIONS: { key: OptionKey; label: string; icon: unknown }[] = [
  { key: 'activates_at', label: 'Activation', icon: CalendarClock },
  { key: 'expires_at', label: 'Expiration', icon: CalendarOff },
  { key: 'visit_limit', label: 'Visit limit', icon: Gauge },
  { key: 'password', label: 'Password', icon: Lock },
  { key: 'folder_id', label: 'Folder', icon: FolderIcon },
  { key: 'fallback_url', label: 'Fallback URL', icon: LifeBuoy },
];

const activeOptions = ref<OptionKey[]>([]);
const availableOptions = computed(() => OPTIONS.filter((o) => !activeOptions.value.includes(o.key)));
const optionDef = (key: OptionKey) => OPTIONS.find((o) => o.key === key)!;

watch(
  () => props.link,
  (link) => {
    if (!link) return;
    tab.value = 'link';
    // useLinkForms has already reset editForm to this link's values.
    activeOptions.value = OPTIONS.map((o) => o.key).filter((key) => props.editForm[key] !== '');
  },
);

function addOption(key: OptionKey) {
  activeOptions.value = [...activeOptions.value, key];
}

function removeOption(key: OptionKey) {
  activeOptions.value = activeOptions.value.filter((k) => k !== key);
  props.editForm[key] = '';
}

// ── Short URL ────────────────────────────────────────────────────────────────
const shortUrlChanged = computed(
  () =>
    Boolean(props.link) &&
    (props.editForm.slug !== props.link!.slug || Number(props.editForm.domain_id) !== props.link!.domain.id),
);

function qrPreviewUrl(qr: Qr) {
  return route('qr-codes.preview', qr.token);
}
</script>

<template>
  <Drawer :show="Boolean(link)" eyebrow="Link settings" :title="link?.short_url" @close="emit('close')">
    <div v-if="link" class="space-y-5 p-5">
      <div class="grid grid-cols-3 rounded-lg border bg-elevated/30 p-1">
        <button
          v-for="entry in [
            { key: 'link' as const, label: 'Link', icon: Link2 },
            { key: 'routing' as const, label: 'Routing', icon: Route },
            { key: 'qr' as const, label: 'QR code', icon: QrCode },
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

      <form v-if="tab === 'link'" class="grid gap-5" @submit.prevent="emit('submit')">
        <div>
          <p class="mb-1.5 text-[13px] font-medium text-foreground">Destination URL</p>
          <DestinationUrlField v-model="editForm.destination_url" :error="editForm.errors.destination_url" />
        </div>

        <div>
          <p class="mb-1.5 text-[13px] font-medium text-foreground">Short URL</p>
          <ShortUrlComposer
            v-model:domain-id="editForm.domain_id"
            v-model:slug="editForm.slug"
            :domains="domains"
            slug-placeholder="slug"
          />
          <p v-if="editForm.errors.slug || editForm.errors.domain_id" class="mt-1.5 text-xs text-danger">
            {{ editForm.errors.slug ?? editForm.errors.domain_id }}
          </p>
          <p v-else-if="shortUrlChanged" class="mt-1.5 text-xs text-warning">
            Changing the short URL breaks copies already shared. QR codes keep working.
          </p>
        </div>

        <label class="flex items-center justify-between gap-3 rounded-xl border bg-surface p-3">
          <span>
            <span class="block text-[13px] font-medium text-foreground">Enabled</span>
            <span class="block text-xs text-faint">Allow this link to resolve when its lifecycle rules allow it.</span>
          </span>
          <Switch v-model="editForm.is_enabled" />
        </label>

        <TransitionGroup
          tag="div"
          class="space-y-3 empty:hidden"
          enter-active-class="transition duration-200 ease-emphasized-out"
          enter-from-class="opacity-0 translate-y-1.5"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-out"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 translate-y-1.5"
        >
          <OptionRow
            v-for="key in activeOptions"
            :key="key"
            :label="optionDef(key).label"
            :icon="optionDef(key).icon"
            @remove="removeOption(key)"
          >
            <template v-if="key === 'activates_at' || key === 'expires_at'">
              <DateTimeField v-model="editForm[key]" />
              <p v-if="editForm.errors[key]" class="mt-1.5 text-xs text-danger">
                {{ editForm.errors[key] }}
              </p>
            </template>

            <template v-else-if="key === 'visit_limit'">
              <StepperInput v-model="editForm.visit_limit" :step="100" placeholder="Unlimited" />
              <p class="mt-1.5 text-xs" :class="editForm.errors.visit_limit ? 'text-danger' : 'text-faint'">
                {{ editForm.errors.visit_limit ?? 'The link stops resolving after this many visits.' }}
              </p>
            </template>

            <template v-else-if="key === 'password'">
              <PasswordInput v-model="editForm.password" placeholder="Visitors must enter this to continue" />
              <p class="mt-1.5 text-xs" :class="editForm.errors.password ? 'text-danger' : 'text-faint'">
                {{ editForm.errors.password ?? 'Masked when already set. Remove this option to drop protection.' }}
              </p>
            </template>

            <template v-else-if="key === 'folder_id'">
              <select v-model="editForm.folder_id" class="h-9">
                <option value="">No folder</option>
                <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                  {{ folder.name }}
                </option>
              </select>
              <p v-if="editForm.errors.folder_id" class="mt-1.5 text-xs text-danger">
                {{ editForm.errors.folder_id }}
              </p>
            </template>

            <template v-else-if="key === 'fallback_url'">
              <input v-model="editForm.fallback_url" class="h-9" placeholder="https://fallback.example" />
              <p class="mt-1.5 text-xs" :class="editForm.errors.fallback_url ? 'text-danger' : 'text-faint'">
                {{ editForm.errors.fallback_url ?? 'Shown when the link is expired or unavailable.' }}
              </p>
            </template>
          </OptionRow>
        </TransitionGroup>

        <OptionChips :options="availableOptions" @add="addOption" />

        <div class="flex justify-end">
          <Button :loading="editForm.processing">Save changes</Button>
        </div>
      </form>

      <form v-if="tab === 'routing'" class="grid gap-5" @submit.prevent="emit('submit')">
        <RoutingRulesEditor v-model="editForm.routing_rules" :errors="editForm.errors" :schema="routingSchema" />

        <div class="flex justify-end">
          <Button :loading="editForm.processing">Save changes</Button>
        </div>
      </form>

      <div v-if="tab === 'qr'" class="grid gap-5">
        <form class="flex items-end gap-2 rounded-xl border bg-surface p-3" @submit.prevent="emit('submitQr')">
          <Field label="New QR code" :error="qrForm.errors.name" class="flex-1">
            <input v-model="qrForm.name" class="h-9" placeholder="Poster, badge, flyer…" />
          </Field>
          <Button variant="secondary" :loading="qrForm.processing">Create &amp; customize</Button>
        </form>

        <div class="grid gap-3">
          <article
            v-for="qr in link.qr_codes"
            :key="qr.id"
            class="card-sheen grid gap-4 rounded-xl border bg-surface p-3 transition-colors hover:border-border-strong sm:grid-cols-[104px_1fr]"
          >
            <Link :href="route('qr-codes.show', qr.token)" class="justify-self-center sm:justify-self-auto">
              <img
                :src="qrPreviewUrl(qr)"
                :alt="`${qr.name} QR code`"
                class="h-24 w-24 rounded-lg bg-white p-1.5 transition-opacity hover:opacity-80"
              />
            </Link>
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-foreground">{{ qr.name }}</p>
              <p class="mt-1 text-xs tabular-nums text-faint">{{ qr.scans }} scans · tracked separately from visits.</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <Link
                  :href="route('qr-codes.show', qr.token)"
                  class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:border-border-strong hover:bg-elevated"
                >
                  <Settings2 class="h-3 w-3" /> Customize
                </Link>
                <a
                  :href="route('qr-codes.export', [qr.token, 'svg'])"
                  class="rounded-md border px-2.5 py-1 text-xs font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                >
                  SVG
                </a>
                <a
                  :href="route('qr-codes.export', [qr.token, 'png'])"
                  class="rounded-md border px-2.5 py-1 text-xs font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                >
                  PNG
                </a>
              </div>
            </div>
          </article>
          <div v-if="link.qr_codes.length === 0" class="rounded-xl border border-dashed px-4 py-8 text-center">
            <QrCode class="mx-auto h-5 w-5 text-faint" />
            <p class="mt-2 text-[13px] text-faint">No QR code yet for this link.</p>
          </div>
        </div>
      </div>
    </div>
  </Drawer>
</template>
