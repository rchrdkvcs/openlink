<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Globe2, Plus, Search, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { BioPagesIndexProps } from '@/Pages/BioPages/types';

const props = withDefaults(defineProps<BioPagesIndexProps>(), { canCreate: true });

const search = ref('');
const status = ref('');
const creating = ref(false);
const createForm = useForm({ displayName: '' });

const filteredPages = computed(() => {
  const query = search.value.trim().toLowerCase();

  return props.bioPages.filter((bioPage) => {
    const matchesSearch = !query || `${bioPage.displayName} ${bioPage.bioUrl}`.toLowerCase().includes(query);
    const matchesStatus =
      !status.value || (status.value === 'changes' ? bioPage.hasUnpublishedChanges : bioPage.status === status.value);
    return matchesSearch && matchesStatus;
  });
});

function createBioPage() {
  if (!createForm.displayName.trim()) return;

  createForm.post(route('bio-pages.store'), {
    onSuccess: () => {
      createForm.reset();
      creating.value = false;
    },
  });
}

function formattedDate(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
}
</script>

<template>
  <Head title="Bio Pages" />

  <AuthenticatedLayout>
    <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
      <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-xl font-semibold tracking-tight">Bio Pages</h1>
          <p class="mt-1 text-sm text-muted">Bring every destination together in one customizable public profile.</p>
        </div>
        <Button v-if="canCreate && !creating" size="sm" type="button" @click="creating = true">
          <Plus class="h-3.5 w-3.5" /> New Bio Page
        </Button>
      </header>

      <form
        v-if="creating"
        class="card-sheen mb-5 flex flex-wrap items-end gap-3 rounded-lg border bg-surface p-4"
        @submit.prevent="createBioPage"
      >
        <label class="grid min-w-64 flex-1 gap-1.5 text-[13px] font-medium">
          Display name
          <input v-model="createForm.displayName" maxlength="80" autofocus placeholder="Alice Martin" />
          <span v-if="createForm.errors.displayName" class="text-xs text-danger">{{
            createForm.errors.displayName
          }}</span>
          <span v-else class="text-xs font-normal text-faint"
            >You can choose the Bio URL and customize everything next.</span
          >
        </label>
        <Button type="submit" size="sm" :loading="createForm.processing">Create</Button>
        <Button type="button" size="sm" variant="ghost" @click="creating = false">Cancel</Button>
      </form>

      <div v-if="bioPages.length" class="mb-5 flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:w-72">
          <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
          <input v-model="search" type="search" class="h-8 pl-8 text-[13px]" placeholder="Search Bio Pages…" />
        </div>
        <select v-model="status" class="h-8 w-48 py-0 text-[13px]">
          <option value="">All statuses</option>
          <option value="published">Published</option>
          <option value="draft">Draft only</option>
          <option value="unavailable">Unavailable</option>
          <option value="changes">Unpublished changes</option>
        </select>
        <span class="text-xs tabular-nums text-faint">
          {{ filteredPages.length }} page{{ filteredPages.length === 1 ? '' : 's' }}
        </span>
      </div>

      <EmptyState
        v-if="!bioPages.length"
        :icon="Sparkles"
        title="Create your first Bio Page"
        description="Share your profile, social accounts, and important destinations from one memorable URL."
      >
        <Button v-if="canCreate" type="button" size="sm" @click="creating = true">
          <Plus class="h-3.5 w-3.5" /> New Bio Page
        </Button>
      </EmptyState>

      <div v-else-if="filteredPages.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="bioPage in filteredPages"
          :key="bioPage.id"
          :href="route('bio-pages.show', bioPage.id)"
          class="card-sheen group rounded-lg border bg-surface p-4 transition hover:border-border-strong hover:bg-elevated/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        >
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border bg-elevated text-muted">
              <Globe2 class="h-4 w-4" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center gap-2">
                <span class="truncate text-sm font-semibold">{{ bioPage.displayName || 'Untitled Bio Page' }}</span>
                <span
                  class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                  :class="
                    bioPage.status === 'published'
                      ? 'bg-success/10 text-success'
                      : bioPage.status === 'unavailable'
                        ? 'bg-warning/10 text-warning'
                        : 'bg-elevated text-faint'
                  "
                >
                  {{ bioPage.status }}
                </span>
              </span>
              <span class="mt-1 block truncate text-xs text-faint">{{
                bioPage.bioUrl || 'Bio URL not configured'
              }}</span>
            </span>
            <ArrowRight class="mt-1 h-4 w-4 shrink-0 text-faint transition-transform group-hover:translate-x-0.5" />
          </div>
          <div class="mt-4 flex items-center justify-between border-t pt-3 text-xs text-faint">
            <span>Updated {{ formattedDate(bioPage.updatedAt) }}</span>
            <span v-if="bioPage.hasUnpublishedChanges" class="font-medium text-warning">Unpublished changes</span>
          </div>
        </Link>
      </div>

      <div v-else class="rounded-lg border border-dashed p-10 text-center">
        <p class="text-sm font-medium">No Bio Pages match these filters.</p>
        <button
          type="button"
          class="mt-2 text-xs text-accent hover:underline"
          @click="
            search = '';
            status = '';
          "
        >
          Clear filters
        </button>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
