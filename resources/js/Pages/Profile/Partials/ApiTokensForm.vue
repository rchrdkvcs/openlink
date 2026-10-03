<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Copy, KeyRound } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import { confirmAction } from '@/lib/confirm';
import { relativeTime } from '@/lib/datetime';
import { copyToClipboard, toast } from '@/lib/toast';

type ApiToken = {
  id: number;
  name: string;
  created_at: string;
  last_used_at: string | null;
};

defineProps<{
  tokens: ApiToken[];
  newToken?: { name: string; token: string } | null;
  canCreate: boolean;
}>();

const form = useForm({ name: '' });

function createToken() {
  form.post(route('profile.api-tokens.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}

async function revoke(token: ApiToken) {
  const confirmed = await confirmAction({
    title: `Revoke ${token.name}?`,
    message: 'Any client using this token loses access immediately. This cannot be undone.',
    confirmLabel: 'Revoke token',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('profile.api-tokens.destroy', token.id), {
    preserveScroll: true,
    onSuccess: () => toast({ title: 'Token revoked', tone: 'success' }),
  });
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
  <div v-if="newToken" role="status" class="rounded-xl border border-success/25 bg-success/10 p-4 sm:p-5">
    <p class="text-sm font-medium text-foreground">{{ newToken.name }} is ready</p>
    <p class="mt-0.5 text-[13px] text-muted">Copy it now. For your security, it won't be shown again.</p>
    <div class="mt-3 flex items-center gap-2">
      <code
        class="min-w-0 flex-1 truncate rounded-lg border bg-background px-3 py-2 font-mono text-[13px] text-foreground"
        >{{ newToken.token }}</code
      >
      <Button type="button" @click="copyToClipboard(newToken.token, 'Token copied')">
        <Copy class="h-4 w-4" /> Copy
      </Button>
    </div>
  </div>

  <form @submit.prevent="createToken">
    <SettingsGroup title="New token">
      <SettingsRow
        label="Name"
        for="token_name"
        :description="
          canCreate ? 'Helps you recognise where the token is used.' : 'Verify your email before creating API tokens.'
        "
        :error="form.errors.name"
      >
        <div class="flex gap-2">
          <Input
            id="token_name"
            v-model="form.name"
            placeholder="Browser extension"
            autocomplete="off"
            :disabled="!canCreate"
          />
          <Button :loading="form.processing" :disabled="!canCreate || !form.name.trim()">Create</Button>
        </div>
      </SettingsRow>
    </SettingsGroup>
  </form>

  <SettingsGroup title="Active tokens">
    <div v-for="token in tokens" :key="token.id" class="flex items-center gap-3 px-4 py-3 sm:px-5">
      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border bg-elevated/60 text-muted">
        <KeyRound class="h-4 w-4" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-foreground">{{ token.name }}</p>
        <p class="truncate text-xs text-faint">
          Created {{ formatDate(token.created_at) }} ·
          {{ token.last_used_at ? `Last used ${relativeTime(token.last_used_at)}` : 'Never used' }}
        </p>
      </div>
      <Button type="button" variant="danger" size="sm" @click="revoke(token)"> Revoke </Button>
    </div>
    <p v-if="tokens.length === 0" class="px-4 py-6 text-center text-[13px] text-muted sm:px-5">No API tokens yet.</p>
  </SettingsGroup>
</template>
