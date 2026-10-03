<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Link2 } from '@lucide/vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { setupDescription, setupStep } from '@/lib/domains';
import type { DomainSetup } from '@/types/payloads';

import DnsRecordsStep from './DnsRecordsStep.vue';
import SetupProgress from './SetupProgress.vue';

const props = defineProps<{ domain: DomainSetup | null }>();

const step = computed(() => setupStep(props.domain));
const pageTitle = computed(() => props.domain?.hostname ?? 'Add a domain');
const pageDescription = computed(() => setupDescription(step.value));

const hostnameForm = useForm({ hostname: '' });

function submitHostname() {
  hostnameForm.post(route('domains.store'));
}
</script>

<template>
  <Head :title="domain ? `Set up ${domain.hostname}` : 'Add a domain'" />

  <SettingsLayout :title="pageTitle" :description="pageDescription">
    <template #actions>
      <Link :href="route('domains.index')">
        <Button variant="ghost" size="sm" type="button"><ArrowLeft class="h-4 w-4" /> Domains</Button>
      </Link>
    </template>

    <SetupProgress :step="step" />

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

    <DnsRecordsStep v-else-if="step === 2 && domain" :domain="domain" />

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
