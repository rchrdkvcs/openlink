<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Link2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Toaster from '@/Components/ui/Toaster.vue';
import { copyToClipboard } from '@/lib/toast';

type Domain = { id: number; hostname: string; is_default: boolean };
type InviteLink = { id: number; role: string; url: string };

const props = defineProps<{
  workspace: { id: number; name: string; slug: string } | null;
  domains: Domain[];
  inviteLinks: InviteLink[];
  hasLink: boolean;
}>();

const step = ref(props.workspace ? 2 : 1);

watch(
  () => props.workspace,
  (workspace) => {
    if (workspace && step.value === 1) {
      step.value = 2;
    }
  },
);

const workspaceForm = useForm({ name: '' });

function createWorkspace() {
  workspaceForm.post(route('onboarding.workspace'));
}

function firstDomainId() {
  return props.domains[0]?.id ?? null;
}

const domainOptions = computed(() => props.domains.map((domain) => ({ value: domain.id, label: domain.hostname })));

const linkForm = useForm({
  domain_id: firstDomainId(),
  destination_url: '',
  slug: '',
});

watch(
  () => props.domains,
  () => {
    if (!linkForm.domain_id) {
      linkForm.domain_id = firstDomainId();
    }
  },
  { immediate: true },
);

function createFirstLink() {
  linkForm.post(route('short-links.store'), {
    preserveScroll: true,
    onSuccess: () => (step.value = 3),
  });
}

const roleOptions = [
  { value: 'admin', label: 'Admin' },
  { value: 'editor', label: 'Editor' },
  { value: 'viewer', label: 'Viewer' },
];

const inviteForm = useForm({ role: 'editor', expires_in_days: null, max_uses: null });

function createInviteLink() {
  inviteForm.post(route('invite-links.store'), { preserveScroll: true });
}

const teamInviteLink = computed(() => props.inviteLinks[0] ?? null);

function copyInviteLink() {
  if (teamInviteLink.value) copyToClipboard(teamInviteLink.value.url, 'Invite link copied');
}

function finish() {
  router.post(route('onboarding.complete'));
}

const steps = [
  { number: 1, label: 'Workspace' },
  { number: 2, label: 'First link' },
  { number: 3, label: 'Team' },
];

const heading = computed(
  () =>
    ({
      1: { title: 'Create your workspace', description: 'A workspace holds your links, domains and team.' },
      2: { title: 'Shorten your first link', description: 'Paste a long URL and we’ll create the short link.' },
      3: { title: 'Invite your team', description: 'Anyone with the link joins with the role you choose.' },
    })[step.value] ?? { title: '', description: '' },
);
</script>

<template>
  <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-4 py-12">
    <Head title="Welcome" />

    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.1),transparent_65%)]"
    />

    <div class="relative w-full max-w-md animate-slide-up">
      <div class="mb-8 flex justify-center">
        <ApplicationLogo class="h-9 w-auto" />
      </div>

      <ol class="mb-6 flex items-center justify-center gap-2" aria-label="Setup progress">
        <template v-for="(item, index) in steps" :key="item.number">
          <li class="flex items-center gap-2" :aria-current="step === item.number ? 'step' : undefined">
            <span
              class="grid h-5 w-5 place-items-center rounded-full text-[11px] font-semibold tabular-nums transition-colors duration-200"
              :class="step >= item.number ? 'bg-foreground text-background' : 'border border-border-strong text-faint'"
            >
              <Check v-if="step > item.number" class="h-3 w-3" />
              <template v-else>{{ item.number }}</template>
            </span>
            <span class="text-xs font-medium" :class="step >= item.number ? 'text-foreground' : 'text-faint'">{{
              item.label
            }}</span>
          </li>
          <li v-if="index < steps.length - 1" class="h-px w-6 bg-border" aria-hidden="true" />
        </template>
      </ol>

      <div class="overflow-hidden rounded-2xl border bg-surface p-6 sm:p-8">
        <Transition
          mode="out-in"
          enter-active-class="transition duration-200 ease-emphasized-out"
          enter-from-class="opacity-0 translate-y-1.5"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-out"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 translate-y-1.5"
        >
          <div :key="step">
            <div class="mb-6 text-center">
              <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">{{ heading.title }}</h1>
              <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ heading.description }}</p>
            </div>

            <form v-if="step === 1" class="grid gap-4" @submit.prevent="createWorkspace">
              <Field label="Workspace name" :error="workspaceForm.errors.name">
                <Input v-model="workspaceForm.name" placeholder="Acme, Marketing, Personal" autofocus required />
              </Field>
              <Button class="mt-1 w-full" :loading="workspaceForm.processing">Create workspace</Button>
            </form>

            <template v-else-if="step === 2">
              <div
                v-if="hasLink"
                class="rounded-lg border border-success/25 bg-success/10 px-3 py-2.5 text-[13px] text-foreground"
              >
                Your first link is ready. You’ll find it on the Links page.
              </div>
              <form v-else class="grid gap-4" @submit.prevent="createFirstLink">
                <Field label="Destination URL" :error="linkForm.errors.destination_url">
                  <Input
                    v-model="linkForm.destination_url"
                    type="url"
                    placeholder="https://example.com/a/very/long/url"
                    required
                    autofocus
                  />
                </Field>
                <Field v-if="domains.length > 1" label="Domain" :error="linkForm.errors.domain_id">
                  <Select v-model="linkForm.domain_id" :options="domainOptions" />
                </Field>
                <Button class="mt-1 w-full" :loading="linkForm.processing">Create link</Button>
              </form>

              <div class="mt-3 flex justify-center">
                <Button v-if="hasLink" class="w-full" type="button" @click="step = 3">Continue</Button>
                <Button v-else variant="ghost" size="sm" type="button" @click="step = 3">Skip for now</Button>
              </div>
            </template>

            <template v-else>
              <div v-if="teamInviteLink" class="grid gap-2">
                <div class="flex items-center gap-2 rounded-lg border border-transparent bg-elevated/70 py-1 pl-3 pr-1">
                  <code class="min-w-0 flex-1 truncate font-mono text-xs text-muted">{{ teamInviteLink.url }}</code>
                  <IconButton title="Copy invite link" @click="copyInviteLink">
                    <Copy class="h-4 w-4" />
                  </IconButton>
                </div>
                <p class="px-1 text-xs text-faint">
                  New members join <span class="font-medium text-muted">{{ workspace?.name }}</span> as
                  <span class="capitalize">{{ teamInviteLink.role }}</span
                  >. Manage invites in Settings.
                </p>
              </div>
              <form v-else class="grid gap-4" @submit.prevent="createInviteLink">
                <Field label="New members join as" :error="inviteForm.errors.role">
                  <Select v-model="inviteForm.role" :options="roleOptions" />
                </Field>
                <Button class="w-full" variant="secondary" :loading="inviteForm.processing">
                  <Link2 class="h-4 w-4" /> Create invite link
                </Button>
              </form>

              <div class="mt-6 flex items-center justify-between border-t pt-4">
                <Button variant="ghost" size="sm" type="button" @click="step = 2">Back</Button>
                <Button size="sm" type="button" @click="finish">
                  {{ teamInviteLink ? 'Finish' : 'Skip and finish' }}
                </Button>
              </div>
            </template>
          </div>
        </Transition>
      </div>

      <p v-if="step === 1" class="mt-6 text-center text-[13px] text-muted">
        Joining a team? Ask for an invite link, or
        <Link :href="route('dashboard')" class="font-medium text-foreground hover:underline hover:underline-offset-4"
          >go to Home</Link
        >.
      </p>
    </div>

    <Toaster />
  </div>
</template>
