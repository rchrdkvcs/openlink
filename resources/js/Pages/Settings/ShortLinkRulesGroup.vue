<script setup lang="ts">
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import Textarea from '@/Components/ui/Textarea.vue';

import type { InstanceSettingsForm } from './instanceSettings';

defineProps<{ form: InstanceSettingsForm }>();
</script>

<template>
  <SettingsGroup title="Short links">
    <SettingsRow
      label="Generated slug length"
      description="Between 4 and 32 characters."
      :error="form.errors.slug_length"
    >
      <div class="sm:ml-auto sm:w-40">
        <StepperInput v-model="form.slug_length" :min="4" />
      </div>
    </SettingsRow>
    <SettingsRow
      label="Reserved slugs"
      description="One per line. These can never be claimed by a short link."
      for="reserved-slugs"
      :error="form.errors.reserved_slugs"
      stacked
    >
      <Textarea
        id="reserved-slugs"
        v-model="form.reserved_slugs"
        class="font-mono text-[13px]"
        rows="5"
        placeholder="admin&#10;login&#10;settings"
      />
    </SettingsRow>
    <SettingsRow
      label="Reserved prefixes"
      description="One per line. Slugs starting with these are rejected."
      for="reserved-prefixes"
      :error="form.errors.reserved_prefixes"
      stacked
    >
      <Textarea
        id="reserved-prefixes"
        v-model="form.reserved_prefixes"
        class="font-mono text-[13px]"
        rows="4"
        placeholder="api/&#10;qr/"
      />
    </SettingsRow>
  </SettingsGroup>
</template>
