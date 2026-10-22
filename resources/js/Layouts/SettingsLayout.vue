<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
  Activity,
  Building2,
  Globe2,
  KeyRound,
  Mail,
  Link as LinkIcon,
  Server,
  ShieldCheck,
  TriangleAlert,
  User,
  Users,
  Webhook,
} from '@lucide/vue';
import { computed } from 'vue';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useShell } from '@/lib/shell';

defineProps<{
  title: string;
  description?: string;
}>();

const { user, canManage, query } = useShell();

type NavItem = { label: string; href: string; icon: unknown; active: boolean };

const accountTab = computed(() => (route().current('profile.edit') ? (query.value.get('tab') ?? 'profile') : null));

const groups = computed(() => {
  const result: { label: string; items: NavItem[] }[] = [];

  if (canManage.value) {
    result.push({
      label: 'Workspace',
      items: [
        {
          label: 'General',
          href: route('settings.workspace'),
          icon: Building2,
          active: route().current('settings.workspace'),
        },
        { label: 'Domains', href: route('domains.index'), icon: Globe2, active: route().current('domains.*') },
        { label: 'Members', href: route('members.index'), icon: Users, active: route().current('members.*') },
        {
          label: 'Webhooks',
          href: route('settings.webhooks'),
          icon: Webhook,
          active: route().current('settings.webhooks'),
        },
        {
          label: 'Weekly summary',
          href: route('settings.summaries'),
          icon: Mail,
          active: route().current('settings.summaries'),
        },
      ],
    });
  }

  result.push({
    label: 'Account',
    items: [
      {
        label: 'Profile',
        href: route('profile.edit', { tab: 'profile' }),
        icon: User,
        active: accountTab.value === 'profile',
      },
      {
        label: 'Sign-in methods',
        href: route('profile.edit', { tab: 'connected-identities' }),
        icon: LinkIcon,
        active: accountTab.value === 'connected-identities',
      },
      {
        label: 'Security',
        href: route('profile.edit', { tab: 'security' }),
        icon: ShieldCheck,
        active: accountTab.value === 'security',
      },
      {
        label: 'API tokens',
        href: route('profile.edit', { tab: 'api-tokens' }),
        icon: KeyRound,
        active: accountTab.value === 'api-tokens',
      },
      {
        label: 'Delete account',
        href: route('profile.edit', { tab: 'danger-zone' }),
        icon: TriangleAlert,
        active: accountTab.value === 'danger-zone',
      },
    ],
  });

  if (user.value.is_instance_admin) {
    result.push({
      label: 'Instance',
      items: [
        {
          label: 'Instance settings',
          href: route('settings.index'),
          icon: Server,
          active: route().current('settings.index'),
        },
        {
          label: 'Diagnostics',
          href: route('settings.diagnostics'),
          icon: Activity,
          active: route().current('settings.diagnostics'),
        },
      ],
    });
  }

  return result;
});
</script>

<template>
  <AuthenticatedLayout panels>
    <div class="flex flex-col gap-8 px-4 py-6 sm:px-6 lg:h-full lg:flex-row lg:gap-2 lg:p-0">
      <nav
        class="shrink-0 lg:w-60 lg:overflow-y-auto lg:overscroll-contain lg:rounded-xl lg:border lg:bg-canvas lg:px-3 lg:py-6"
        aria-label="Settings"
      >
        <p class="mb-4 hidden px-2 text-[22px] font-semibold tracking-[-0.015em] lg:block">Settings</p>
        <div class="flex gap-6 overflow-x-auto pb-1 lg:block lg:space-y-6 lg:overflow-visible lg:pb-0">
          <div v-for="group in groups" :key="group.label" class="shrink-0">
            <p class="mb-1 truncate px-2 text-xs font-medium text-faint">{{ group.label }}</p>
            <div class="flex gap-px lg:block lg:space-y-px">
              <Link
                v-for="item in group.items"
                :key="item.label"
                :href="item.href"
                :aria-current="item.active ? 'page' : undefined"
                class="group flex h-8 shrink-0 items-center gap-2.5 whitespace-nowrap rounded-lg px-2 text-[13px] font-medium transition-colors duration-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                :class="
                  item.active ? 'bg-elevated text-foreground' : 'text-muted hover:bg-elevated/50 hover:text-foreground'
                "
              >
                <component
                  :is="item.icon"
                  class="h-4 w-4 shrink-0"
                  :class="item.active ? 'text-foreground' : 'text-faint group-hover:text-muted'"
                />
                {{ item.label }}
              </Link>
            </div>
          </div>
        </div>
      </nav>

      <div
        class="min-w-0 flex-1 [scrollbar-gutter:stable] lg:overflow-y-auto lg:overscroll-contain lg:rounded-xl lg:border lg:bg-canvas lg:px-8 lg:py-8"
      >
        <div class="w-full">
          <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
              <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">{{ title }}</h1>
              <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2"><slot name="actions" /></div>
          </header>
          <div class="space-y-8">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
