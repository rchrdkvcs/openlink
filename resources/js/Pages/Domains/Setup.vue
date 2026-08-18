<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Globe, Link2, RefreshCw } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import Field from '@/Components/ui/Field.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

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
  { number: 1, label: 'Domain name' },
  { number: 2, label: 'DNS records' },
  { number: 3, label: 'Done' },
];

// Step 1 — choose hostname
const hostnameForm = useForm({ hostname: '' });

function submitHostname() {
  hostnameForm.post(route('domains.store'));
}

// Step 2 — DNS records with live checks
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

const copiedKey = ref<string | null>(null);

async function copy(key: string, value: string) {
  await navigator.clipboard.writeText(value);
  copiedKey.value = key;
  setTimeout(() => (copiedKey.value = null), 2000);
}

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
  <Head title="Add domain" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
      <Link
        :href="route('domains.index')"
        class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted hover:text-foreground"
      >
        <ArrowLeft class="h-4 w-4" /> Back to domains
      </Link>

      <div class="mb-6 flex items-center gap-2">
        <template v-for="(item, index) in steps" :key="item.number">
          <div class="flex items-center gap-2">
            <span
              class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold"
              :class="step >= item.number ? 'bg-foreground text-background' : 'border border-border text-faint'"
            >
              <Check v-if="step > item.number" class="h-3.5 w-3.5" />
              <template v-else>{{ item.number }}</template>
            </span>
            <span class="text-xs" :class="step >= item.number ? 'text-foreground' : 'text-faint'">{{
              item.label
            }}</span>
          </div>
          <span v-if="index < steps.length - 1" class="h-px w-8 bg-border" />
        </template>
      </div>

      <div class="card-sheen rounded-xl border bg-surface p-6">
        <!-- Step 1: hostname -->
        <template v-if="step === 1">
          <div class="flex items-center gap-2">
            <Globe class="h-4 w-4 text-faint" />
            <h1 class="text-lg font-semibold text-foreground">What domain do you want to use?</h1>
          </div>
          <p class="mt-1 text-sm text-muted">
            This is the address your short links will start with. Most teams use a subdomain like
            <code class="rounded bg-elevated px-1 py-0.5 font-mono text-xs">go.yourcompany.com</code> — you must own the
            domain to complete the next step.
          </p>
          <form class="mt-5 space-y-4" @submit.prevent="submitHostname">
            <Field label="Domain" :error="hostnameForm.errors.hostname">
              <input v-model="hostnameForm.hostname" class="h-9" placeholder="go.example.com" autofocus required />
            </Field>
            <Button class="w-full" :loading="hostnameForm.processing">Continue</Button>
          </form>
        </template>

        <!-- Step 2: DNS records -->
        <template v-else-if="step === 2 && domain">
          <h1 class="text-lg font-semibold text-foreground">Add two DNS records for {{ domain.hostname }}</h1>
          <p class="mt-1 text-sm text-muted">
            Sign in to the website where you bought your domain (GoDaddy, OVH, Cloudflare, Namecheap…), find the
            <strong>DNS settings</strong>, and add both records below. You only need to do this once.
          </p>

          <div class="mt-5 space-y-4">
            <div
              v-for="record in records"
              :key="record.key"
              class="rounded-lg border p-4"
              :class="record.done ? 'border-success/30 bg-success/5' : 'border-border'"
            >
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span
                    class="grid h-5 w-5 place-items-center rounded-full"
                    :class="record.done ? 'bg-success text-white' : 'border border-border text-faint'"
                  >
                    <Check v-if="record.done" class="h-3 w-3" />
                  </span>
                  <span class="text-sm font-medium text-foreground">{{ record.type }} record</span>
                </div>
                <Badge :variant="record.done ? 'success' : 'warning'" dot>{{
                  record.done ? 'found' : 'waiting'
                }}</Badge>
              </div>
              <p class="mt-1 text-xs text-muted">{{ record.purpose }}</p>

              <dl class="mt-3 grid gap-2 text-xs">
                <div class="grid grid-cols-[64px_1fr_auto] items-center gap-2">
                  <dt class="font-medium uppercase tracking-wide text-faint">Type</dt>
                  <dd>
                    <code class="rounded bg-elevated px-1.5 py-0.5 font-mono">{{ record.type }}</code>
                  </dd>
                  <span />
                </div>
                <div class="grid grid-cols-[64px_1fr_auto] items-center gap-2">
                  <dt class="font-medium uppercase tracking-wide text-faint">Name</dt>
                  <dd class="min-w-0">
                    <code class="block truncate rounded bg-elevated px-1.5 py-0.5 font-mono">{{ record.name }}</code>
                  </dd>
                  <button
                    type="button"
                    class="text-faint hover:text-foreground"
                    title="Copy"
                    @click="copy(record.key + '-name', record.name)"
                  >
                    <CopyCheckIcon :copied="copiedKey === record.key + '-name'" />
                  </button>
                </div>
                <div class="grid grid-cols-[64px_1fr_auto] items-center gap-2">
                  <dt class="font-medium uppercase tracking-wide text-faint">Value</dt>
                  <dd class="min-w-0">
                    <code class="block truncate rounded bg-elevated px-1.5 py-0.5 font-mono">{{ record.value }}</code>
                  </dd>
                  <button
                    type="button"
                    class="text-faint hover:text-foreground"
                    title="Copy"
                    @click="copy(record.key + '-value', record.value)"
                  >
                    <CopyCheckIcon :copied="copiedKey === record.key + '-value'" />
                  </button>
                </div>
              </dl>

              <p v-if="record.error && !record.done" class="mt-2 text-xs text-warning">
                {{ record.error }}
              </p>
            </div>
          </div>

          <div class="mt-5 flex items-center justify-between gap-3">
            <p class="text-xs text-faint">
              We check automatically every 15 seconds. DNS changes usually apply within minutes, but can take up to 24
              hours.
            </p>
            <Button variant="secondary" size="sm" type="button" :loading="checking" @click="checkNow">
              <RefreshCw class="h-3.5 w-3.5" /> Check now
            </Button>
          </div>
        </template>

        <!-- Step 3: done -->
        <template v-else-if="domain">
          <div class="flex animate-slide-up flex-col items-center py-4 text-center">
            <Transition
              appear
              enter-active-class="transition duration-200 ease-emphasized-out"
              enter-from-class="opacity-0 scale-[0.97]"
              enter-to-class="opacity-100 scale-100"
            >
              <span class="grid h-12 w-12 place-items-center rounded-full bg-success/15 text-success">
                <Check class="h-6 w-6" />
              </span>
            </Transition>
            <h1 class="mt-4 text-lg font-semibold text-foreground">{{ domain.hostname }} is ready</h1>
            <p class="mt-1 max-w-sm text-sm text-muted">
              Your domain is verified and pointing to this server. You can now create short links on it. HTTPS may take
              a few minutes to become available on the first visit.
            </p>
            <div class="mt-6 flex gap-3">
              <Button variant="secondary" @click="router.visit(route('domains.index'))">View domains</Button>
              <Button @click="router.visit(route('links.index'))"> <Link2 class="h-4 w-4" /> Create a link </Button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
