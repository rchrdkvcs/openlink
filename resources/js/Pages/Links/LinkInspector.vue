<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
  Archive,
  CalendarClock,
  ChevronRight,
  Copy,
  ExternalLink,
  Gauge,
  LifeBuoy,
  Lock,
  QrCode,
  Route,
  Trash2,
  X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import Button from '@/Components/ui/Button.vue';
import DateTimeField from '@/Components/ui/DateTimeField.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Input from '@/Components/ui/Input.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
import Select from '@/Components/ui/Select.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import Switch from '@/Components/ui/Switch.vue';
import TagInput from '@/Components/ui/TagInput.vue';
import { humanize } from '@/lib/datetime';
import { archiveLink, createQrCodeFor, deleteLink } from '@/lib/linkActions';
import { displayUrl, isLikelyUrl, normalizeUrl } from '@/lib/links';
import { hasOpenFloatingLayer } from '@/lib/overlays';
import { copyToClipboard, toast } from '@/lib/toast';

import InspectorRow from './InspectorRow.vue';
import { routingRuleSummary } from './routing';
import RoutingDialog from './RoutingDialog.vue';
import ShortUrlComposer from './ShortUrlComposer.vue';
import type { Domain, Folder, RoutingRuleDraft, RoutingSchema, ShortLink } from './types';

const props = defineProps<{
  link: ShortLink;
  domains: Domain[];
  folders: Folder[];
  knownTags: { id: number; name: string }[];
  routingSchema: RoutingSchema;
  canEdit: boolean;
}>();

const emit = defineEmits<{ close: [] }>();

const PASSWORD_MASK = '********';

const form = useForm({
  folder_id: '',
  domain_id: '' as number | string,
  slug: '',
  destination_url: '',
  fallback_url: '',
  is_enabled: true,
  activates_at: '',
  expires_at: '',
  visit_limit: '',
  password: '',
  tags: '',
  routing_rules: [] as RoutingRuleDraft[],
});

const openRows = ref({ schedule: false, limit: false, password: false, fallback: false });
const routingOpen = ref(false);

function cloneRoutingRules(rules: RoutingRuleDraft[]): RoutingRuleDraft[] {
  return rules.map((rule) => ({
    ...rule,
    conditions: JSON.parse(JSON.stringify(rule.conditions ?? [])),
    variants: (rule.variants ?? []).map((variant) => ({ ...variant })),
  }));
}

function load(link: ShortLink, resetRows: boolean) {
  form.defaults({
    folder_id: link.folder?.id ? String(link.folder.id) : '',
    domain_id: link.domain.id,
    slug: link.slug,
    destination_url: link.destination_url,
    fallback_url: link.fallback_url ?? '',
    is_enabled: link.is_enabled,
    activates_at: link.activates_at ? String(link.activates_at).slice(0, 16) : '',
    expires_at: link.expires_at ? String(link.expires_at).slice(0, 16) : '',
    visit_limit: link.visit_limit ? String(link.visit_limit) : '',
    password: link.has_password ? PASSWORD_MASK : '',
    tags: link.tags.map((tag) => tag.name).join(', '),
    routing_rules: cloneRoutingRules(link.routing_rules ?? []),
  });
  form.reset();
  form.clearErrors();
  if (resetRows) {
    openRows.value = { schedule: false, limit: false, password: false, fallback: false };
    routingOpen.value = false;
  }
}

watch(
  () => props.link,
  (link, previous) => load(link, link.id !== previous?.id),
  { immediate: true },
);

const folderOptions = computed(() => [
  { value: '', label: 'No folder' },
  ...props.folders.map((folder) => ({ value: String(folder.id), label: folder.name })),
]);

const shortUrlChanged = computed(
  () => form.slug !== props.link.slug || Number(form.domain_id) !== props.link.domain.id,
);

const scheduleSummary = computed(() => {
  if (form.activates_at && form.expires_at)
    return `${humanize(form.activates_at).split(' ·')[0]} → ${humanize(form.expires_at).split(' ·')[0]}`;
  if (form.activates_at) return `Starts ${humanize(form.activates_at)}`;
  if (form.expires_at) return `Ends ${humanize(form.expires_at)}`;
  return 'Always on';
});

const enabledRules = computed(() => form.routing_rules.filter((rule) => rule.is_enabled).length);

const routingHasErrors = computed(() => Object.keys(form.errors).some((key) => key.startsWith('routing_rules')));

const routingPreview = computed(() =>
  form.routing_rules.slice(0, 3).map((rule, index) => ({
    key: rule.id ?? rule.client_id ?? index,
    name: rule.name || 'Untitled routing rule',
    enabled: rule.is_enabled,
    summary: rule.is_enabled ? routingRuleSummary(props.routingSchema, rule) : 'Disabled',
  })),
);

function save() {
  if (!props.canEdit || !form.isDirty || form.processing) return;

  form
    .transform((data) => {
      const payload = { ...data, destination_url: normalizeUrl(data.destination_url) };
      if (props.link.has_password && data.password === PASSWORD_MASK) {
        const { password: _password, ...rest } = payload;
        return rest;
      }
      return payload;
    })
    .patch(route('short-links.update', props.link.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast({ title: 'Changes saved', tone: 'success', duration: 2000 }),
      onError: () => {
        if (form.errors.activates_at || form.errors.expires_at) openRows.value.schedule = true;
        if (form.errors.visit_limit) openRows.value.limit = true;
        if (form.errors.password) openRows.value.password = true;
        if (form.errors.fallback_url) openRows.value.fallback = true;
        if (Object.keys(form.errors).some((key) => key.startsWith('routing_rules'))) routingOpen.value = true;
      },
    });
}

function toggleEnabled(value: boolean) {
  form.is_enabled = value;
}

function onKeydown(event: KeyboardEvent) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
    event.preventDefault();
    save();
    return;
  }

  const target = event.target as HTMLElement | null;
  if (
    event.key === 'Escape' &&
    !hasOpenFloatingLayer() &&
    !document.querySelector('[role="dialog"], [role="alertdialog"]') &&
    !target?.closest('input, textarea')
  ) {
    emit('close');
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

const destinationValid = computed(() => isLikelyUrl(normalizeUrl(form.destination_url)));
</script>

<template>
  <aside class="flex h-full flex-col overflow-hidden bg-canvas" aria-label="Link details">
    <header class="flex items-center gap-3 px-5 pb-4 pt-5">
      <Favicon :url="form.destination_url || link.destination_url" />
      <div class="min-w-0 flex-1">
        <a
          :href="link.short_url"
          target="_blank"
          rel="noopener"
          class="block truncate text-sm font-semibold text-foreground hover:text-accent"
          >{{ displayUrl(link.short_url) }}</a
        >
        <p class="truncate text-xs text-faint">{{ displayUrl(link.destination_url) }}</p>
      </div>
      <IconButton title="Close" @click="emit('close')"><X class="h-4 w-4" /></IconButton>
    </header>

    <div class="flex items-center gap-1.5 border-b px-5 pb-3">
      <Button variant="secondary" size="sm" type="button" class="flex-1" @click="copyToClipboard(link.short_url)">
        <Copy class="h-3.5 w-3.5" /> Copy
      </Button>
      <a
        :href="link.short_url"
        target="_blank"
        rel="noopener"
        class="inline-flex h-7 flex-1 items-center justify-center gap-1.5 rounded-lg bg-elevated px-2.5 text-[13px] font-medium text-foreground transition-colors hover:bg-border-strong"
      >
        <ExternalLink class="h-3.5 w-3.5" /> Open
      </a>
      <Button v-if="canEdit" variant="secondary" size="sm" type="button" class="flex-1" @click="createQrCodeFor(link)">
        <QrCode class="h-3.5 w-3.5" /> QR code
      </Button>
    </div>

    <form class="min-h-0 flex-1 overflow-y-auto overscroll-contain" @submit.prevent="save">
      <fieldset :disabled="!canEdit" class="contents">
        <div class="grid grid-cols-3 divide-x border-b">
          <div class="px-5 py-3">
            <p class="text-xs text-faint">Visits</p>
            <p class="mt-0.5 text-[15px] font-semibold tabular-nums">{{ link.visits.toLocaleString() }}</p>
          </div>
          <div class="px-5 py-3">
            <p class="text-xs text-faint">Scans</p>
            <p class="mt-0.5 text-[15px] font-semibold tabular-nums">{{ link.scans.toLocaleString() }}</p>
          </div>
          <label class="flex cursor-pointer flex-col px-5 py-3">
            <span class="text-xs text-faint">{{ form.is_enabled ? 'Enabled' : 'Disabled' }}</span>
            <span class="mt-1.5 flex items-center gap-2">
              <Switch :model-value="form.is_enabled" @update:model-value="toggleEnabled" />
            </span>
          </label>
        </div>

        <div class="space-y-4 px-5 py-4">
          <Field label="Destination" :error="form.errors.destination_url">
            <Input v-model="form.destination_url" spellcheck="false" placeholder="https://example.com/page" />
          </Field>

          <div class="grid gap-1.5">
            <span class="text-[13px] font-medium text-foreground">Short link</span>
            <ShortUrlComposer
              v-model:domain-id="form.domain_id"
              v-model:slug="form.slug"
              :domains="domains"
              :disabled="!canEdit"
              slug-placeholder="slug"
            />
            <p v-if="form.errors.slug || form.errors.domain_id" class="text-xs text-danger">
              {{ form.errors.slug ?? form.errors.domain_id }}
            </p>
            <p v-else-if="shortUrlChanged" class="text-xs text-warning">
              Links already shared will stop working. QR codes keep working.
            </p>
          </div>

          <div class="grid gap-4">
            <Field label="Folder" :error="form.errors.folder_id">
              <Select v-model="form.folder_id" :options="folderOptions" :disabled="!canEdit" />
            </Field>
            <Field label="Tags" :error="form.errors.tags">
              <TagInput v-model="form.tags" :suggestions="knownTags" />
            </Field>
          </div>
        </div>

        <div class="mx-5 mb-4 divide-y overflow-hidden rounded-xl border bg-surface">
          <InspectorRow
            v-model:open="openRows.schedule"
            :icon="CalendarClock"
            label="Schedule"
            :summary="scheduleSummary"
            :active="Boolean(form.activates_at || form.expires_at)"
          >
            <Field label="Starts" hint="Leave empty to activate immediately." :error="form.errors.activates_at">
              <DateTimeField v-model="form.activates_at" />
            </Field>
            <Field label="Ends" hint="The link stops resolving after this date." :error="form.errors.expires_at">
              <DateTimeField v-model="form.expires_at" />
            </Field>
          </InspectorRow>

          <InspectorRow
            v-model:open="openRows.limit"
            :icon="Gauge"
            label="Visit limit"
            :summary="form.visit_limit ? `${link.successful_visits} / ${form.visit_limit}` : 'Unlimited'"
            :active="Boolean(form.visit_limit)"
          >
            <StepperInput v-model="form.visit_limit" :step="100" placeholder="Unlimited" />
            <p class="text-xs" :class="form.errors.visit_limit ? 'text-danger' : 'text-faint'">
              {{ form.errors.visit_limit ?? 'The link stops resolving after this many visits. Clear to remove.' }}
            </p>
          </InspectorRow>

          <InspectorRow
            v-model:open="openRows.password"
            :icon="Lock"
            label="Password"
            :summary="form.password ? 'Protected' : 'None'"
            :active="Boolean(form.password)"
          >
            <PasswordInput v-model="form.password" placeholder="Visitors must enter this to continue" />
            <p class="text-xs" :class="form.errors.password ? 'text-danger' : 'text-faint'">
              {{ form.errors.password ?? 'Clear the field to remove protection.' }}
            </p>
          </InspectorRow>

          <InspectorRow
            v-model:open="openRows.fallback"
            :icon="LifeBuoy"
            label="Fallback"
            :summary="form.fallback_url ? displayUrl(form.fallback_url) : 'Unavailable page'"
            :active="Boolean(form.fallback_url)"
          >
            <Input v-model="form.fallback_url" placeholder="https://example.com/expired" />
            <p class="text-xs" :class="form.errors.fallback_url ? 'text-danger' : 'text-faint'">
              {{ form.errors.fallback_url ?? 'Where visitors go when the link is expired, scheduled or disabled.' }}
            </p>
          </InspectorRow>

          <div>
            <button
              type="button"
              class="flex h-10 w-full items-center gap-2.5 px-3.5 text-left transition-colors hover:bg-elevated/40 focus-visible:bg-elevated/40 focus-visible:outline-none"
              aria-haspopup="dialog"
              @click="routingOpen = true"
            >
              <Route class="h-4 w-4 shrink-0" :class="enabledRules > 0 ? 'text-accent' : 'text-faint'" />
              <span class="flex-1 text-[13px] font-medium text-foreground">Smart routing</span>
              <span
                class="max-w-[55%] truncate text-[13px]"
                :class="routingHasErrors ? 'text-danger' : enabledRules > 0 ? 'text-muted' : 'text-faint'"
                >{{
                  routingHasErrors
                    ? 'Needs attention'
                    : enabledRules
                      ? `${enabledRules} rule${enabledRules === 1 ? '' : 's'}`
                      : 'Off'
                }}</span
              >
              <ChevronRight class="h-3.5 w-3.5 shrink-0 text-faint" />
            </button>
            <ul v-if="routingPreview.length" class="grid gap-2 pb-3 pe-3.5 ps-[1.625rem]">
              <li v-for="rule in routingPreview" :key="rule.key" class="flex min-w-0 items-start gap-2">
                <span
                  class="mt-[5px] h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="rule.enabled ? 'bg-accent' : 'bg-border-strong'"
                  aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-xs font-medium text-foreground">{{ rule.name }}</span>
                  <span class="block truncate text-xs text-faint">{{ rule.summary }}</span>
                </span>
              </li>
              <li v-if="form.routing_rules.length > 3" class="ps-3.5 text-xs text-faint">
                {{ form.routing_rules.length - 3 }} more
              </li>
            </ul>
          </div>
        </div>

        <div v-if="canEdit" class="flex items-center gap-1 px-3 pb-6">
          <Button
            v-if="link.status !== 'archived'"
            variant="ghost"
            size="sm"
            type="button"
            @click="archiveLink(link, () => emit('close'))"
          >
            <Archive class="h-3.5 w-3.5" /> Archive
          </Button>
          <Button
            variant="ghost"
            size="sm"
            type="button"
            class="hover:bg-danger/10 hover:text-danger"
            @click="deleteLink(link, () => emit('close'))"
          >
            <Trash2 class="h-3.5 w-3.5" /> Delete
          </Button>
        </div>
      </fieldset>
    </form>

    <RoutingDialog
      v-model:open="routingOpen"
      v-model="form.routing_rules"
      :errors="form.errors"
      :schema="routingSchema"
      :default-destination="form.destination_url"
    />

    <Transition
      enter-active-class="transition duration-200 ease-emphasized-out"
      enter-from-class="translate-y-full"
      leave-active-class="transition duration-150 ease-out"
      leave-to-class="translate-y-full"
    >
      <div v-if="canEdit && form.isDirty" class="flex items-center gap-2 border-t bg-overlay px-5 py-3">
        <p class="min-w-0 flex-1 truncate text-[13px] text-muted">
          {{ Object.keys(form.errors).length ? 'Some fields need attention.' : 'Unsaved changes' }}
        </p>
        <Button variant="ghost" size="sm" type="button" :disabled="form.processing" @click="load(link, false)">
          Discard
        </Button>
        <Button size="sm" type="button" :loading="form.processing" :disabled="!destinationValid" @click="save">
          Save
        </Button>
      </div>
    </Transition>
  </aside>
</template>
