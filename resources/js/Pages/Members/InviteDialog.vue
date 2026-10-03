<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Eye, Link2, PencilLine, ShieldCheck } from '@lucide/vue';
import { watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { type AssignableRole, defaultInviteRole, roleDescriptions, roleLabel } from '@/lib/permissions';
import { copyToClipboard, toast } from '@/lib/toast';
import type { InviteLink } from '@/types/payloads';

import { expiryOptions, inviteLinkRequest, usesOptions } from './inviteLinks';

const props = defineProps<{ workspaceName: string; inviteLinks: InviteLink[] }>();
const open = defineModel<boolean>('open', { required: true });

const inviteRoles = (
  [
    ['editor', PencilLine],
    ['viewer', Eye],
    ['admin', ShieldCheck],
  ] as const
).map(([value, icon]) => ({ value: value as AssignableRole, label: roleLabel(value), icon }));

const form = useForm({ role: defaultInviteRole as string, expires_in_days: '' as string, max_uses: '' as string });

watch(open, (value) => {
  if (!value) return;
  form.reset();
  form.clearErrors();
});

function submit() {
  const existing = new Set(props.inviteLinks.map((link) => link.id));

  form
    .transform((data) => inviteLinkRequest(data))
    .post(route('invite-links.store'), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        open.value = false;
        const created = props.inviteLinks.find((link) => !existing.has(link.id));
        toast({
          title: 'Invite link created',
          description: created?.url,
          tone: 'success',
          duration: 8000,
          action: created
            ? { label: 'Copy', run: () => void copyToClipboard(created.url, 'Invite link copied') }
            : undefined,
        });
      },
    });
}
</script>

<template>
  <Dialog
    v-model:open="open"
    size="lg"
    :title="`Invite people to ${workspaceName}`"
    description="Create a link and share it. People who open it join with the role you pick."
  >
    <form class="space-y-5 px-5 pb-5 pt-4" @submit.prevent="submit">
      <fieldset>
        <legend class="mb-2 text-[13px] font-medium text-foreground">Join as</legend>
        <div class="grid gap-1.5" role="radiogroup">
          <label
            v-for="option in inviteRoles"
            :key="option.value"
            class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent/40"
            :class="
              form.role === option.value
                ? 'border-accent/50 bg-accent/[0.06]'
                : 'border-border hover:border-border-strong hover:bg-elevated/40'
            "
          >
            <input v-model="form.role" type="radio" name="invite-role" :value="option.value" class="sr-only" />
            <component
              :is="option.icon"
              class="h-4 w-4 shrink-0"
              :class="form.role === option.value ? 'text-accent' : 'text-faint'"
            />
            <span class="min-w-0 flex-1">
              <span class="block text-[13px] font-medium text-foreground">{{ option.label }}</span>
              <span class="block text-xs text-muted">{{ roleDescriptions[option.value] }}</span>
            </span>
            <span
              class="grid h-4 w-4 shrink-0 place-items-center rounded-full border"
              :class="form.role === option.value ? 'border-accent bg-accent' : 'border-border-strong'"
            >
              <span v-if="form.role === option.value" class="h-1.5 w-1.5 rounded-full bg-white" />
            </span>
          </label>
        </div>
        <p v-if="form.errors.role" class="mt-1.5 text-xs text-danger">{{ form.errors.role }}</p>
      </fieldset>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <p class="mb-2 text-[13px] font-medium text-foreground">Expires</p>
          <SegmentedControl v-model="form.expires_in_days" :options="expiryOptions" label="Expires" class="w-full" />
          <p v-if="form.errors.expires_in_days" class="mt-1.5 text-xs text-danger">
            {{ form.errors.expires_in_days }}
          </p>
        </div>
        <div>
          <p class="mb-2 text-[13px] font-medium text-foreground">Number of uses</p>
          <SegmentedControl v-model="form.max_uses" :options="usesOptions" label="Number of uses" class="w-full" />
          <p v-if="form.errors.max_uses" class="mt-1.5 text-xs text-danger">{{ form.errors.max_uses }}</p>
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-1">
        <Button variant="ghost" type="button" @click="open = false">Cancel</Button>
        <Button :loading="form.processing"><Link2 v-if="!form.processing" /> Create invite link</Button>
      </div>
    </form>
  </Dialog>
</template>
