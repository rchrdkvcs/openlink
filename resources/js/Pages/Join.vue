<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Link2Off, UserPlus } from '@lucide/vue';
import { computed } from 'vue';

import AuthIcon from '@/Components/Auth/AuthIcon.vue';
import AuthLink from '@/Components/Auth/AuthLink.vue';
import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { roleLabel } from '@/lib/permissions';

const props = defineProps<{
  invite: {
    token: string;
    workspace: string;
    role: string;
    usable: boolean;
  };
  isMember: boolean;
  canRegister: boolean;
}>();

const page = usePage();
const isAuthenticated = computed(() => Boolean((page.props.auth as { user: unknown | null }).user));

const title = computed(() => {
  if (!props.invite.usable) {
    return 'This invite has expired';
  }

  return props.isMember ? 'You’re already a member' : `Join ${props.invite.workspace}`;
});

function join() {
  router.post(route('join.store', props.invite.token));
}
</script>

<template>
  <GuestLayout :title="title">
    <Head :title="`Join ${invite.workspace}`" />

    <template #icon>
      <AuthIcon v-if="invite.usable" tone="accent"><UserPlus /></AuthIcon>
      <AuthIcon v-else><Link2Off /></AuthIcon>
    </template>

    <template #description>
      <template v-if="!invite.usable">
        It may have reached its limit or been revoked. Ask a workspace admin for a new link.
      </template>
      <template v-else-if="isMember">
        You already belong to <span class="font-medium text-foreground">{{ invite.workspace }}</span
        >.
      </template>
      <template v-else>
        You’ve been invited as <span class="font-medium text-foreground">{{ roleLabel(invite.role) }}</span
        >.<template v-if="!isAuthenticated"> Sign in or create an account to continue.</template>
      </template>
    </template>

    <Button v-if="invite.usable && (isMember || isAuthenticated)" class="w-full" size="lg" type="button" @click="join">
      {{ isMember ? 'Open workspace' : 'Join workspace' }} <ArrowRight />
    </Button>

    <template v-else-if="invite.usable">
      <div class="grid gap-2">
        <Button
          v-if="canRegister"
          class="w-full"
          size="lg"
          type="button"
          @click="router.visit(route('register', { invite: invite.token }))"
        >
          Create an account
        </Button>
        <Button class="w-full" size="lg" variant="secondary" type="button" @click="router.visit(route('login'))">
          Sign in
        </Button>
      </div>
      <p class="mt-4 text-xs leading-relaxed text-faint">
        {{
          canRegister
            ? 'Already have an account? Sign in, then open this invite again.'
            : 'Registration is closed. Sign in with an existing account, then open this invite again.'
        }}
      </p>
    </template>

    <template v-if="!invite.usable" #footer>
      <AuthLink :href="route('home')">Back to Openlink</AuthLink>
    </template>
  </GuestLayout>
</template>
