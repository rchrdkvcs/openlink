<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Link2Off, UserPlus } from '@lucide/vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

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

function join() {
  router.post(route('join.store', props.invite.token));
}
</script>

<template>
  <GuestLayout>
    <Head :title="`Join ${invite.workspace}`" />

    <div class="text-center">
      <span
        class="mx-auto mb-4 grid h-11 w-11 place-items-center rounded-xl border"
        :class="invite.usable ? 'border-accent/25 bg-accent/10 text-accent' : 'bg-elevated text-muted'"
      >
        <UserPlus v-if="invite.usable" class="h-5 w-5" />
        <Link2Off v-else class="h-5 w-5" />
      </span>

      <template v-if="!invite.usable">
        <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">This invite has expired</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-muted">
          It may have reached its limit or been revoked. Ask a workspace admin for a new link.
        </p>
        <Link
          :href="route('home')"
          class="mt-6 inline-flex text-[13px] font-medium text-foreground hover:underline hover:underline-offset-4"
        >
          Back to Openlink
        </Link>
      </template>

      <template v-else-if="isMember">
        <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">You’re already a member</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-muted">
          You already belong to <span class="font-medium text-foreground">{{ invite.workspace }}</span
          >.
        </p>
        <Button class="mt-6 w-full" type="button" @click="join">Open workspace</Button>
      </template>

      <template v-else-if="isAuthenticated">
        <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">Join {{ invite.workspace }}</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-muted">
          You’ve been invited as <span class="font-medium capitalize text-foreground">{{ invite.role }}</span
          >.
        </p>
        <Button class="mt-6 w-full" type="button" @click="join">Join workspace</Button>
      </template>

      <template v-else>
        <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">Join {{ invite.workspace }}</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-muted">
          You’ve been invited as <span class="font-medium capitalize text-foreground">{{ invite.role }}</span
          >. Sign in or create an account to continue.
        </p>
        <div class="mt-6 grid gap-2">
          <Button
            v-if="canRegister"
            class="w-full"
            type="button"
            @click="router.visit(route('register', { invite: invite.token }))"
          >
            Create an account
          </Button>
          <Button class="w-full" variant="secondary" type="button" @click="router.visit(route('login'))">
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
    </div>
  </GuestLayout>
</template>
