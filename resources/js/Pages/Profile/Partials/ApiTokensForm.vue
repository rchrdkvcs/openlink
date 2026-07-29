<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { KeyRound, Trash2 } from '@lucide/vue';

import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Button from '@/Components/ui/Button.vue';

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

function revoke(token: ApiToken) {
  router.delete(route('profile.api-tokens.destroy', token.id), { preserveScroll: true });
}

function formatDate(value: string | null) {
  if (!value) return 'Never';
  return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-base font-semibold text-foreground">API Tokens</h2>
      <p class="mt-1 text-sm text-muted">Create revocable credentials for external clients.</p>
    </header>

    <div v-if="newToken" class="mt-6 rounded-lg border border-success/25 bg-success/10 p-4">
      <p class="text-sm font-medium text-success">{{ newToken.name }} token created</p>
      <code class="mt-3 block break-all rounded-md border bg-background px-3 py-2 font-mono text-sm text-foreground">{{
        newToken.token
      }}</code>
    </div>

    <form class="mt-6 flex max-w-md items-end gap-3" @submit.prevent="createToken">
      <div class="flex-1">
        <InputLabel for="token_name" value="Token name" />
        <TextInput
          id="token_name"
          v-model="form.name"
          class="mt-1.5 block w-full"
          placeholder="Browser extension"
          :disabled="!canCreate"
        />
        <InputError :message="form.errors.name" class="mt-2" />
        <p v-if="!canCreate" class="mt-2 text-xs text-warning">Verify your email before creating API tokens.</p>
      </div>
      <Button :loading="form.processing" :disabled="!canCreate"> <KeyRound class="h-4 w-4" /> Create </Button>
    </form>

    <div class="mt-6 divide-y divide-border/60 rounded-lg border">
      <div v-for="token in tokens" :key="token.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-foreground">{{ token.name }}</p>
          <p class="text-xs text-faint">
            Created {{ formatDate(token.created_at) }} · Last used {{ formatDate(token.last_used_at) }}
          </p>
        </div>
        <Button type="button" variant="danger" size="sm" @click="revoke(token)">
          <Trash2 class="h-3.5 w-3.5" /> Revoke
        </Button>
      </div>
      <p v-if="tokens.length === 0" class="px-4 py-6 text-sm text-muted">No API tokens yet.</p>
    </div>
  </section>
</template>
