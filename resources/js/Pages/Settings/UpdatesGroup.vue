<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';

import { isUpdateInProgress, updateMessage, type UpdateStatus } from './instanceUpdates';

const props = defineProps<{ updateStatus: UpdateStatus | null }>();

const requestingUpdate = ref(false);
let updatePoll: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
  updatePoll = setInterval(() => {
    if (isUpdateInProgress(props.updateStatus)) {
      router.reload({ only: ['updateStatus'] });
    }
  }, 5000);
});

onUnmounted(() => {
  if (updatePoll) clearInterval(updatePoll);
});

function requestUpdate() {
  requestingUpdate.value = true;
  router.post(
    route('instance-update.store'),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        requestingUpdate.value = false;
      },
    },
  );
}

const updateInProgress = computed(() => isUpdateInProgress(props.updateStatus));
const updateFailed = computed(() => props.updateStatus?.state === 'failed');
const message = computed(() => updateMessage(props.updateStatus));
</script>

<template>
  <SettingsGroup title="Updates">
    <SettingsRow
      label="Installed version"
      :description="updateFailed ? undefined : message"
      :error="updateFailed ? message : undefined"
    >
      <div class="flex items-center gap-3 sm:justify-end">
        <span class="font-mono text-[13px] text-foreground">{{ updateStatus?.current ?? 'dev' }}</span>
        <Button
          v-if="updateStatus?.available && updateStatus.canUpdate"
          type="button"
          size="sm"
          :loading="requestingUpdate || updateInProgress"
          @click="requestUpdate"
        >
          Update now
        </Button>
      </div>
    </SettingsRow>
    <SettingsRow label="Latest stable release">
      <div class="flex sm:justify-end">
        <a
          v-if="updateStatus?.latest"
          :href="updateStatus.latest.url"
          target="_blank"
          rel="noopener noreferrer"
          class="font-mono text-[13px] text-accent hover:underline"
        >
          {{ updateStatus.latest.version }}
        </a>
        <span v-else class="text-[13px] text-faint">Unavailable</span>
      </div>
    </SettingsRow>
  </SettingsGroup>
</template>
