<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import BioPagePreview from '@/Components/BioPages/BioPagePreview.vue';
import type { BioDraft } from '@/Pages/BioPages/types';

const props = defineProps<{
  bioPage: BioDraft;
  bioUrl: string;
  shareTitle?: string;
  shareDescription?: string;
  openGraphImageUrl?: string;
}>();

const title = props.shareTitle || props.bioPage.shareTitle || props.bioPage.displayName;
const description = props.shareDescription || props.bioPage.shareDescription || props.bioPage.biography;
</script>

<template>
  <Head :title="title">
    <meta v-if="description" head-key="description" name="description" :content="description" />
    <meta head-key="robots" name="robots" :content="bioPage.isIndexable ? 'index,follow' : 'noindex,nofollow'" />
    <meta head-key="og:type" property="og:type" content="profile" />
    <meta head-key="og:title" property="og:title" :content="title" />
    <meta v-if="description" head-key="og:description" property="og:description" :content="description" />
    <meta head-key="og:url" property="og:url" :content="bioUrl" />
    <meta
      v-if="openGraphImageUrl || bioPage.profileImageUrl"
      head-key="og:image"
      property="og:image"
      :content="openGraphImageUrl || bioPage.profileImageUrl || ''"
    />
    <meta
      v-if="openGraphImageUrl || bioPage.profileImageUrl"
      head-key="twitter:image"
      name="twitter:image"
      :content="openGraphImageUrl || bioPage.profileImageUrl || ''"
    />
    <meta head-key="twitter:card" name="twitter:card" content="summary" />
    <link head-key="canonical" rel="canonical" :href="bioUrl" />
  </Head>

  <main class="min-h-screen">
    <BioPagePreview :page="bioPage" :bio-url="bioUrl" class="min-h-screen" interactive />
  </main>
</template>
