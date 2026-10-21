<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Smartphone, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import {
  associationInput,
  emptyDraft,
  firstFieldError,
  formatAssociationFile,
  platformLabel,
  platformOptions,
} from '@/lib/appAssociations';
import { confirmAction } from '@/lib/confirm';
import { copyToClipboard, toast } from '@/lib/toast';
import type { AppAssociation, AppAssociationFile } from '@/types/payloads';

const props = defineProps<{
  domain: { id: number; hostname: string; status: string; can_publish: boolean };
  associations: AppAssociation[];
  files: { apple: AppAssociationFile; android: AppAssociationFile };
  reserved_paths: string[];
}>();

const form = useForm(emptyDraft());
const errors = computed(() => form.errors as Record<string, string | undefined>);
const isIos = computed(() => form.platform === 'ios');

const publishedFiles = computed(() => [
  { key: 'apple', label: 'iOS', file: props.files.apple },
  { key: 'android', label: 'Android', file: props.files.android },
]);

function submit() {
  form
    .transform((draft) => associationInput(draft))
    .post(route('domains.app-associations.store', props.domain.id), {
      preserveScroll: true,
      onSuccess: () => {
        const platform = form.platform;
        form.defaults(emptyDraft(platform));
        form.reset();
        toast({ title: 'App added' });
      },
    });
}

async function remove(association: AppAssociation) {
  const confirmed = await confirmAction({
    title: `Remove ${association.app_id}?`,
    message: 'Links on this domain stop opening this app once devices refresh their association data.',
    confirmLabel: 'Remove app',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('domains.app-associations.destroy', [props.domain.id, association.id]), {
    preserveScroll: true,
    onSuccess: () => toast({ title: 'App removed' }),
  });
}
</script>

<template>
  <Head :title="`Apps for ${domain.hostname}`" />

  <SettingsLayout
    :title="`Apps for ${domain.hostname}`"
    description="Let your own iOS or Android app open short URLs on this domain."
  >
    <template #actions>
      <Link :href="route('domains.index')">
        <Button variant="ghost" size="sm" type="button"><ArrowLeft class="h-4 w-4" /> Domains</Button>
      </Link>
    </template>

    <div class="space-y-6">
      <p
        v-if="!domain.can_publish"
        class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-[13px] text-foreground"
        role="status"
      >
        This domain isn’t active, so its association files aren’t published. Apps can be added once it’s active.
      </p>

      <SectionCard title="Associated apps">
        <ul v-if="associations.length" class="divide-y divide-border">
          <li v-for="association in associations" :key="association.id" class="flex items-start gap-4 px-5 py-3.5">
            <Badge variant="outline" class="mt-0.5 shrink-0">{{ platformLabel(association.platform) }}</Badge>
            <div class="min-w-0 flex-1">
              <p class="truncate font-mono text-[13px] text-foreground" :title="association.app_id">
                {{ association.app_id }}
              </p>
              <p v-if="association.paths" class="mt-0.5 break-all font-mono text-xs text-muted">
                {{ association.paths.join('  ') }}
              </p>
              <p v-else class="mt-0.5 text-xs text-muted">
                {{ association.sha256_cert_fingerprints?.length ?? 0 }} signing certificate{{
                  association.sha256_cert_fingerprints?.length === 1 ? '' : 's'
                }}
                · paths come from the app’s intent filters
              </p>
            </div>
            <button
              type="button"
              class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-faint transition-colors duration-150 hover:bg-elevated hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
              :aria-label="`Remove ${association.app_id}`"
              @click="remove(association)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </li>
        </ul>
        <EmptyState v-else title="No apps yet" description="Add an app to publish its association file on this domain.">
          <template #icon><Smartphone class="h-5 w-5" /></template>
        </EmptyState>
      </SectionCard>

      <SectionCard v-if="domain.can_publish" title="Add an app">
        <form class="space-y-5 p-5" @submit.prevent="submit">
          <SegmentedControl v-model="form.platform" :options="platformOptions" label="Platform" />
          <p v-if="errors.domain" class="text-xs text-danger">{{ errors.domain }}</p>

          <div v-if="isIos" class="grid gap-4 sm:grid-cols-2">
            <Field
              label="Team ID"
              hint="The 10-character App ID prefix from your Apple Developer account."
              :error="errors.team_id"
            >
              <Input v-model="form.team_id" placeholder="ABCDE12345" autocomplete="off" spellcheck="false" required />
            </Field>
            <Field label="Bundle ID" :error="errors.identifier">
              <Input
                v-model="form.identifier"
                placeholder="com.example.app"
                autocomplete="off"
                spellcheck="false"
                required
              />
            </Field>
          </div>
          <Field v-else label="Package name" :error="errors.identifier">
            <Input
              v-model="form.identifier"
              placeholder="com.example.app"
              autocomplete="off"
              spellcheck="false"
              required
            />
          </Field>

          <Field
            v-if="isIos"
            label="Paths"
            :hint="`One per line. Use * and ? as wildcards, like /promo/*. ${reserved_paths.join(', ')} always open in the browser.`"
            :error="firstFieldError(errors, 'paths')"
          >
            <Textarea v-model="form.paths" placeholder="/promo/*" spellcheck="false" class="font-mono" required />
          </Field>
          <Field
            v-else
            label="SHA-256 certificate fingerprints"
            hint="One per line. With Play App Signing, use the app signing key from Play Console. Paths are set in your app’s intent filters."
            :error="firstFieldError(errors, 'sha256_cert_fingerprints')"
          >
            <Textarea
              v-model="form.sha256_cert_fingerprints"
              placeholder="14:6D:E9:83:…:44:E5"
              spellcheck="false"
              class="font-mono"
              required
            />
          </Field>

          <div class="flex justify-end">
            <Button :loading="form.processing" :disabled="!form.identifier.trim()">Add app</Button>
          </div>
        </form>
      </SectionCard>

      <SectionCard
        title="Published files"
        description="Generated from the apps above. Your reverse proxy must serve them over HTTPS without redirects."
      >
        <div class="divide-y divide-border">
          <div v-for="entry in publishedFiles" :key="entry.key" class="space-y-2 px-5 py-4">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <p class="text-[13px] font-medium text-foreground">{{ entry.label }}</p>
                <p class="truncate font-mono text-xs text-muted" :title="entry.file.url">{{ entry.file.url }}</p>
              </div>
              <Badge :variant="entry.file.content ? 'success' : 'default'" dot class="shrink-0">
                {{ entry.file.content ? 'Published' : 'Not published' }}
              </Badge>
            </div>
            <div v-if="entry.file.content" class="relative">
              <pre
                class="max-h-64 overflow-auto rounded-lg border bg-elevated/40 p-3 pr-10 font-mono text-xs leading-relaxed text-foreground"
                >{{ formatAssociationFile(entry.file.content) }}</pre>
              <button
                type="button"
                class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-md text-faint transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
                :aria-label="`Copy ${entry.label} association file`"
                @click="copyToClipboard(formatAssociationFile(entry.file.content), 'File copied')"
              >
                <Copy class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </div>
      </SectionCard>

      <SectionCard title="What visitors get">
        <ul class="list-disc space-y-1.5 py-4 pl-9 pr-5 text-[13px] leading-relaxed text-muted">
          <li>With the app installed and a matching link, the platform may open the app with the short URL.</li>
          <li>
            Without the app, in a browser, or when the platform declines, the short URL resolves as usual: schedule,
            expiry, password, visit limit, and routing rules all apply.
          </li>
          <li>
            Opening the app isn’t a visit and doesn’t unlock protected links. The app receives the short URL, never the
            destination.
          </li>
          <li>
            These checks only confirm the files are well-formed. Test on real devices: app builds, platform caches, and
            user settings also decide what opens.
          </li>
        </ul>
      </SectionCard>
    </div>
  </SettingsLayout>
</template>
