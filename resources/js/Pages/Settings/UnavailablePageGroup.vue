<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

import PoweredBy from '@/Components/PublicState/PoweredBy.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import Switch from '@/Components/ui/Switch.vue';

import type { InstanceSettingsForm } from './instanceSettings';

defineProps<{ form: InstanceSettingsForm }>();
</script>

<template>
  <SettingsGroup
    title="Unavailable page"
    description="Shown when a short link is expired, disabled or not yet scheduled."
  >
    <SettingsRow
      label="Show Openlink branding"
      description="Show “powered by opnlnk.app” at the bottom of error and unavailable link pages."
      :error="form.errors.public_error_branding"
    >
      <div class="flex sm:justify-end">
        <Switch v-model="form.public_error_branding" aria-label="Show Openlink branding" />
      </div>
    </SettingsRow>
    <SettingsRow label="Preview" stacked>
      <div class="relative overflow-hidden rounded-lg border bg-background">
        <div
          class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-[radial-gradient(ellipse_at_top,hsl(var(--warning)/0.08),transparent_65%)]"
        />
        <div class="grid place-items-center px-6 py-10">
          <div class="relative w-full max-w-xs rounded-xl border bg-surface p-5 text-center">
            <div class="mx-auto mb-3 grid h-9 w-9 place-items-center rounded-lg border bg-elevated text-warning">
              <CircleAlert class="h-4 w-4" />
            </div>
            <p class="break-words text-sm font-semibold text-foreground">This link is unavailable</p>
            <p class="mt-1.5 break-words text-[13px] text-muted">The link cannot be opened right now.</p>
          </div>
        </div>
        <PoweredBy v-if="form.public_error_branding" class="px-6 pb-5" />
      </div>
    </SettingsRow>
  </SettingsGroup>
</template>
