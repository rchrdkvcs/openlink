<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Link2, RefreshCw } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { copyToClipboard } from '@/lib/toast';

type Domain = {
  id: number;
  hostname: string;
  status: string;
  expected_txt_name: string;
  expected_txt: string;
  failure_reason: string | null;
  ownership_verified: boolean;
  dns_pointed: boolean;
  dns_check_error: string | null;
  dns_record: { type: string; value: string };
};

const props = defineProps<{ domain: Domain | null }>();

const step = computed(() => {
  if (!props.domain) return 1;
  return props.domain.status === 'active' ? 3 : 2;
});

const steps = [
  { number: 1, label: 'Hostname' },
  { number: 2, label: 'DNS records' },
  { number: 3, label: 'Ready' },
];

const pageTitle = computed(() => props.domain?.hostname ?? 'Add a domain');

const pageDescription = computed(() => {
  if (step.value === 1) return 'Use a domain or subdomain you own for branded short links.';
  if (step.value === 2) return 'Add two DNS records where you manage this domain. You only do this once.';
  return 'Verified and serving short links.';
});

const hostnameForm = useForm({ hostname: '' });

function submitHostname() {
  hostnameForm.post(route('domains.store'));
}

const checking = ref(false);

function checkNow() {
  if (!props.domain || checking.value) return;
  checking.value = true;
  router.post(
    route('domains.verify', props.domain.id),
    {},
    { preserveScroll: true, onFinish: () => (checking.value = false) },
  );
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  pollTimer = setInterval(() => {
    if (step.value === 2 && !document.hidden) checkNow();
  }, 15000);
});

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
});

const records = computed(() => {
  if (!props.domain) return [];
  return [
    {
      key: 'txt',
      purpose: 'Proves you own the domain',
      type: 'TXT',
      name: props.domain.expected_txt_name,
      value: props.domain.expected_txt,
      done: props.domain.ownership_verified,
      error: props.domain.ownership_verified ? null : props.domain.failure_reason,
    },
    {
      key: 'pointing',
      purpose: 'Sends visitors to this server',
      type: props.domain.dns_record.type,
      name: props.domain.hostname,
      value: props.domain.dns_record.value,
      done: props.domain.dns_pointed || props.domain.status === 'active',
      error: props.domain.dns_check_error,
    },
  ];
});
</script>

<template>
  <Head :title="domain ? `Set up ${domain.hostname}` : 'Add a domain'" />

  <SettingsLayout :title="pageTitle" :description="pageDescription">
    <template #actions>
      <Link :href="route('domains.index')">
        <Button variant="ghost" size="sm" type="button"><ArrowLeft class="h-4 w-4" /> Domains</Button>
      </Link>
    </template>

    <ol class="flex items-center gap-2" aria-label="Setup progress">
      <template v-for="(item, index) in steps" :key="item.number">
        <li class="flex items-center gap-2" :aria-current="step === item.number ? 'step' : undefined">
          <span
            class="grid h-5 w-5 place-items-center rounded-full text-[11px] font-semibold transition-colors duration-200"
            :class="
              step > item.number
                ? 'bg-success text-white'
                : step === item.number
                  ? 'bg-foreground text-background'
                  : 'border border-border-strong text-faint'
            "
          >
            <Check v-if="step > item.number" class="h-3 w-3" />
            <template v-else>{{ item.number }}</template>
          </span>
          <span class="text-[13px]" :class="step >= item.number ? 'font-medium text-foreground' : 'text-faint'">
            {{ item.label }}
          </span>
        </li>
        <li v-if="index < steps.length - 1" aria-hidden="true" class="h-px w-8 bg-border" />
      </template>
    </ol>

    <section v-if="step === 1" class="rounded-xl border bg-surface p-5">
      <form class="space-y-5" @submit.prevent="submitHostname">
        <Field
          label="Hostname"
          hint="Most teams use a subdomain like go.yourcompany.com. You need access to its DNS settings."
          :error="hostnameForm.errors.hostname"
        >
          <Input
            v-model="hostnameForm.hostname"
            placeholder="go.example.com"
            autocomplete="off"
            spellcheck="false"
            autofocus
            required
          />
        </Field>
        <div class="flex justify-end">
          <Button :loading="hostnameForm.processing" :disabled="!hostnameForm.hostname.trim()">Continue</Button>
        </div>
      </form>
    </section>

    <template v-else-if="step === 2 && domain">
      <p class="text-sm text-muted">
        Sign in where you bought the domain (Cloudflare, OVH, GoDaddy, Namecheap…), open its DNS settings and add both
        records below.
      </p>

      <section
        v-for="record in records"
        :key="record.key"
        class="overflow-hidden rounded-xl border bg-surface transition-colors duration-200"
        :class="record.done ? 'border-success/30' : ''"
      >
        <header class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
          <div class="flex min-w-0 items-center gap-3">
            <span
              class="grid h-5 w-5 shrink-0 place-items-center rounded-full transition-colors duration-200"
              :class="record.done ? 'bg-success text-white' : 'border border-border-strong'"
            >
              <Check v-if="record.done" class="h-3 w-3" />
            </span>
            <div class="min-w-0">
              <h2 class="text-sm font-medium text-foreground">{{ record.type }} record</h2>
              <p class="text-[13px] text-muted">{{ record.purpose }}</p>
            </div>
          </div>
          <Badge :variant="record.done ? 'success' : 'warning'" dot class="shrink-0">
            {{ record.done ? 'Found' : 'Waiting' }}
          </Badge>
        </header>

        <dl class="divide-y divide-border border-t">
          <div class="grid grid-cols-[64px_minmax(0,1fr)_32px] items-center gap-3 px-4 py-2 sm:px-5">
            <dt class="text-[13px] text-muted">Type</dt>
            <dd class="truncate font-mono text-[13px] text-foreground">{{ record.type }}</dd>
            <span />
          </div>
          <div class="grid grid-cols-[64px_minmax(0,1fr)_32px] items-center gap-3 px-4 py-2 sm:px-5">
            <dt class="text-[13px] text-muted">Name</dt>
            <dd class="truncate font-mono text-[13px] text-foreground" :title="record.name">{{ record.name }}</dd>
            <button
              type="button"
              class="grid h-8 w-8 place-items-center rounded-lg text-faint transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
              :aria-label="`Copy ${record.type} record name`"
              @click="copyToClipboard(record.name, 'Name copied')"
            >
              <Copy class="h-3.5 w-3.5" />
            </button>
          </div>
          <div class="grid grid-cols-[64px_minmax(0,1fr)_32px] items-center gap-3 px-4 py-2 sm:px-5">
            <dt class="text-[13px] text-muted">Value</dt>
            <dd class="truncate font-mono text-[13px] text-foreground" :title="record.value">{{ record.value }}</dd>
            <button
              type="button"
              class="grid h-8 w-8 place-items-center rounded-lg text-faint transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
              :aria-label="`Copy ${record.type} record value`"
              @click="copyToClipboard(record.value, 'Value copied')"
            >
              <Copy class="h-3.5 w-3.5" />
            </button>
          </div>
        </dl>

        <p v-if="record.error && !record.done" class="border-t px-4 py-2.5 text-xs text-warning sm:px-5">
          {{ record.error }}
        </p>
      </section>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs leading-relaxed text-faint">
          Checked automatically every 15 seconds. DNS changes usually apply within minutes, but can take up to 24 hours.
        </p>
        <Button variant="secondary" size="sm" type="button" class="shrink-0" :loading="checking" @click="checkNow">
          <RefreshCw v-if="!checking" class="h-3.5 w-3.5" /> Check now
        </Button>
      </div>
    </template>

    <section v-else-if="domain" class="rounded-xl border bg-surface px-6 py-10">
      <div class="flex flex-col items-center text-center">
        <Transition
          appear
          enter-active-class="transition duration-300 ease-emphasized-out"
          enter-from-class="opacity-0 scale-[0.9]"
          enter-to-class="opacity-100 scale-100"
        >
          <span class="grid h-12 w-12 place-items-center rounded-full bg-success/15 text-success">
            <Check class="h-6 w-6" />
          </span>
        </Transition>
        <h2 class="mt-4 text-[15px] font-semibold text-foreground">{{ domain.hostname }} is ready</h2>
        <p class="mt-1 max-w-sm text-[13px] leading-relaxed text-muted">
          You can now create short links on it. HTTPS may take a few minutes on the first visit.
        </p>
        <div class="mt-6 flex gap-2">
          <Button variant="secondary" type="button" @click="router.visit(route('domains.index'))">View domains</Button>
          <Button type="button" @click="router.visit(route('links.index'))"
            ><Link2 class="h-4 w-4" /> Create a link</Button
          >
        </div>
      </div>
    </section>
  </SettingsLayout>
</template>
