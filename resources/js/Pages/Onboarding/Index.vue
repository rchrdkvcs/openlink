<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Copy } from '@lucide/vue';
import { computed } from 'vue';

import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Toaster from '@/Components/ui/Toaster.vue';
import { displayUrl } from '@/lib/links';
import { roleDescriptions, roleLabel, roleOptions } from '@/lib/permissions';

import OnboardingPreview from './OnboardingPreview.vue';
import OnboardingProgress from './OnboardingProgress.vue';
import { type OnboardingProps, useOnboardingFlow } from './useOnboardingFlow';

const props = defineProps<OnboardingProps>();

const {
  step,
  direction,
  definition,
  actions,
  completed,
  processing,
  workspaceForm,
  linkForm,
  inviteForm,
  domainOptions,
  selectedHostname,
  teamInviteLink,
  submit,
  skip,
  back,
  copy,
} = useOnboardingFlow(props);

const offset = computed(() =>
  direction.value === 'forward'
    ? { enter: 'translate-x-4', leave: '-translate-x-4' }
    : { enter: '-translate-x-4', leave: 'translate-x-4' },
);

const workspaceName = computed(() => props.workspace?.name ?? workspaceForm.name.trim());
</script>

<template>
  <div class="grid min-h-screen grid-cols-1 bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
    <Head title="Welcome" />

    <main class="relative min-w-0">
      <div
        class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.08),transparent_65%)] lg:hidden"
      />

      <form class="relative flex min-h-screen flex-col" novalidate @submit.prevent="submit">
        <header class="px-5 pt-6 sm:px-10 sm:pt-8">
          <ApplicationLogo class="h-8 w-auto" />
        </header>

        <div class="flex flex-1 px-5 py-12 sm:px-10 lg:pt-[16vh]">
          <div class="mx-auto w-full max-w-[420px] animate-slide-up">
            <OnboardingProgress :step="step" :completed="completed" />

            <div class="mt-10">
              <Transition
                mode="out-in"
                enter-active-class="transition-[opacity,transform,filter] duration-300 ease-emphasized-out"
                :enter-from-class="`opacity-0 blur-[2px] ${offset.enter}`"
                enter-to-class="translate-x-0 opacity-100 blur-0"
                leave-active-class="transition-[opacity,transform,filter] duration-150 ease-out"
                leave-from-class="translate-x-0 opacity-100 blur-0"
                :leave-to-class="`opacity-0 blur-[2px] ${offset.leave}`"
              >
                <section :key="step" :aria-labelledby="`onboarding-step-${step}`">
                  <h1
                    :id="`onboarding-step-${step}`"
                    class="text-[28px] font-semibold leading-tight tracking-[-0.02em] text-foreground"
                  >
                    {{ definition.title }}
                  </h1>
                  <p class="mt-2 text-sm leading-relaxed text-muted">{{ definition.description }}</p>

                  <div class="mt-8">
                    <Field v-if="step === 1" label="Workspace name" :error="workspaceForm.errors.name">
                      <Input
                        v-model="workspaceForm.name"
                        size="lg"
                        placeholder="Acme, Marketing, Personal…"
                        maxlength="255"
                        autocomplete="organization"
                        autofocus
                        required
                      />
                    </Field>

                    <template v-else-if="step === 2">
                      <div v-if="firstLink" class="rounded-xl border border-success/20 bg-success/[0.06] p-4">
                        <p class="flex items-center gap-2 text-[13px] font-medium text-success">
                          <Check class="h-3.5 w-3.5" /> Your first link is live
                        </p>
                        <div class="mt-3 flex items-center gap-2 rounded-lg bg-background/60 py-1 pl-3 pr-1">
                          <a
                            :href="firstLink.short_url"
                            target="_blank"
                            rel="noopener"
                            class="min-w-0 flex-1 truncate font-mono text-[13px] text-foreground hover:text-accent"
                            >{{ displayUrl(firstLink.short_url) }}</a
                          >
                          <IconButton title="Copy short link" @click="copy(firstLink.short_url, 'Short link copied')">
                            <Copy class="h-4 w-4" />
                          </IconButton>
                        </div>
                        <p class="mt-2 truncate px-1 text-xs text-faint">
                          Redirects to {{ displayUrl(firstLink.destination_url) }}
                        </p>
                      </div>

                      <div v-else class="grid gap-4">
                        <Field label="Destination URL" :error="linkForm.errors.destination_url">
                          <Input
                            v-model="linkForm.destination_url"
                            size="lg"
                            type="url"
                            inputmode="url"
                            placeholder="https://example.com/a/very/long/url"
                            autocomplete="url"
                            autofocus
                            required
                          />
                        </Field>
                        <Field v-if="domainOptions.length > 1" label="Domain" :error="linkForm.errors.domain_id">
                          <Select v-model="linkForm.domain_id" size="lg" :options="domainOptions" />
                        </Field>
                      </div>
                    </template>

                    <template v-else>
                      <div v-if="teamInviteLink" class="rounded-xl border border-success/20 bg-success/[0.06] p-4">
                        <p class="flex items-center gap-2 text-[13px] font-medium text-success">
                          <Check class="h-3.5 w-3.5" /> Invite link ready
                        </p>
                        <div class="mt-3 flex items-center gap-2 rounded-lg bg-background/60 py-1 pl-3 pr-1">
                          <code class="min-w-0 flex-1 truncate font-mono text-xs text-muted">{{
                            teamInviteLink.url
                          }}</code>
                          <IconButton title="Copy invite link" @click="copy(teamInviteLink.url, 'Invite link copied')">
                            <Copy class="h-4 w-4" />
                          </IconButton>
                        </div>
                        <p class="mt-2 px-1 text-xs text-faint">
                          New members join <span class="font-medium text-muted">{{ workspace?.name }}</span> as
                          {{ roleLabel(teamInviteLink.role) }}. Manage invites anytime in Settings.
                        </p>
                      </div>

                      <fieldset v-else class="grid gap-2">
                        <legend class="mb-1.5 text-[13px] font-medium text-foreground">New members join as</legend>
                        <label
                          v-for="option in roleOptions"
                          :key="option.value"
                          class="flex cursor-pointer items-start gap-3 rounded-xl border px-3.5 py-3 transition-[background-color,border-color] duration-150 hover:bg-elevated/50 has-[:checked]:border-accent/50 has-[:checked]:bg-accent/[0.06] has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent/25"
                        >
                          <input
                            v-model="inviteForm.role"
                            type="radio"
                            name="role"
                            :value="option.value"
                            class="peer sr-only"
                          />
                          <span
                            class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full border border-border-strong transition-colors duration-150 peer-checked:border-accent peer-checked:bg-accent"
                          >
                            <span class="h-1.5 w-1.5 rounded-full bg-background" />
                          </span>
                          <span class="grid gap-0.5">
                            <span class="text-[13px] font-medium text-foreground">{{ option.label }}</span>
                            <span class="text-xs leading-relaxed text-faint">{{ roleDescriptions[option.value] }}</span>
                          </span>
                        </label>
                        <p v-if="inviteForm.errors.role" class="text-xs text-danger">{{ inviteForm.errors.role }}</p>
                      </fieldset>
                    </template>
                  </div>
                </section>
              </Transition>
            </div>

            <p v-if="step === 1" class="mt-6 text-[13px] text-muted">
              Joining a team? Ask a teammate for an invite link, or
              <Link
                :href="route('dashboard')"
                class="font-medium text-foreground hover:underline hover:underline-offset-4"
                >go to Home</Link
              >.
            </p>
          </div>
        </div>

        <footer class="sticky bottom-0 border-t bg-background/80 px-5 py-4 backdrop-blur sm:px-10">
          <div class="mx-auto flex w-full max-w-[420px] items-center gap-2">
            <Button v-if="actions.back" variant="ghost" size="lg" type="button" :disabled="processing" @click="back">
              <ArrowLeft /> <span class="max-sm:sr-only">Back</span>
            </Button>
            <div class="ml-auto flex items-center gap-2">
              <Button v-if="actions.skip" variant="ghost" size="lg" type="button" :disabled="processing" @click="skip">
                Skip for now
              </Button>
              <Button size="lg" type="submit" :loading="processing">
                {{ actions.primary }}
                <ArrowRight v-if="!processing" />
              </Button>
            </div>
          </div>
        </footer>
      </form>
    </main>

    <aside class="sticky top-0 hidden h-screen p-3 lg:flex">
      <OnboardingPreview
        class="flex-1"
        :step="step"
        :workspace-name="workspaceName"
        :hostname="selectedHostname"
        :destination-url="firstLink?.destination_url ?? linkForm.destination_url"
        :short-url="firstLink?.short_url"
        :invite-url="teamInviteLink?.url"
        :invite-role="teamInviteLink?.role ?? inviteForm.role"
      />
    </aside>

    <Toaster />
  </div>
</template>
