<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { ComposerLink } from '@/Components/Links/LinkComposer.vue';
import LinkComposer from '@/Components/Links/LinkComposer.vue';
import LogoMark from '@/Components/LogoMark.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { greetingFor } from '@/lib/greetings';
import { useShell } from '@/lib/shell';
import type { Domain, Folder } from '@/types/payloads';

import MonthlyInsights from './Dashboard/MonthlyInsights.vue';
import RecentLinks from './Dashboard/RecentLinks.vue';
import type { DashboardAnalytics, LinkCounts, RecentLink } from './Dashboard/types';

defineProps<{
  domains: Domain[];
  folders: Folder[];
  linkCounts: LinkCounts;
  recentLinks: RecentLink[];
  analytics: DashboardAnalytics;
}>();

const { user, workspace, canEdit } = useShell();

const firstName = computed(() => user.value.name.split(' ')[0]);
const greeting = greetingFor(firstName.value);

function openLink(id: number) {
  router.visit(route('links.index', { link: id }));
}

function onEdit(link: ComposerLink) {
  openLink(link.id);
}
</script>

<template>
  <Head title="Home" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
      <section class="mx-auto max-w-3xl">
        <div class="mb-5 flex justify-center" aria-hidden="true">
          <span class="grid h-10 w-10 place-items-center rounded-xl bg-foreground text-background">
            <LogoMark class="h-[22px] w-auto" />
          </span>
          <UserAvatar
            :name="user.name"
            :src="user.profile_avatar_url"
            size="lg"
            class="-ml-2 !h-10 !w-10 border-0 ring-[3px] ring-canvas"
          />
        </div>
        <h1 class="text-center text-2xl font-semibold tracking-[-0.02em] text-foreground">
          {{ greeting }}
        </h1>
        <p class="mt-1 text-center text-sm text-muted">
          Paste a link, get a short one. It’s copied to your clipboard the moment it’s ready.
        </p>

        <LinkComposer
          v-if="canEdit"
          class="mt-7"
          size="lg"
          autofocus
          :domains="domains"
          :folders="folders"
          :preferred-domain-id="workspace?.preferred_domain_id ?? null"
          @edit="onEdit"
        />
      </section>

      <RecentLinks :links="recentLinks" :total="linkCounts.total" @open="openLink" />

      <MonthlyInsights :analytics="analytics" :link-counts="linkCounts" @open="openLink" />
    </div>
  </AuthenticatedLayout>
</template>
