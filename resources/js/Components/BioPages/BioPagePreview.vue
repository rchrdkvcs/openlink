<script setup lang="ts">
import { Globe2, Mail, Phone, Share2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import type { BioDraft, BioElement } from '@/Pages/BioPages/types';

const props = withDefaults(
  defineProps<{
    page: BioDraft;
    bioUrl?: string;
    interactive?: boolean;
    compact?: boolean;
  }>(),
  { bioUrl: '', interactive: false, compact: false },
);

const prefersLight = ref(false);
let colorSchemeQuery: MediaQueryList | null = null;

function syncColorScheme(event?: MediaQueryListEvent) {
  prefersLight.value = event?.matches ?? colorSchemeQuery?.matches ?? false;
}

onMounted(() => {
  colorSchemeQuery = window.matchMedia('(prefers-color-scheme: light)');
  syncColorScheme();
  colorSchemeQuery.addEventListener('change', syncColorScheme);
});

onBeforeUnmount(() => colorSchemeQuery?.removeEventListener('change', syncColorScheme));

const usesLightAppearance = computed(
  () => props.page.theme.appearance === 'light' || (props.page.theme.appearance === 'auto' && prefersLight.value),
);

const effectiveTheme = computed(() => {
  const theme = props.page.theme;
  if (!usesLightAppearance.value) return theme;

  return {
    ...theme,
    backgroundColor: theme.backgroundColor.toLowerCase() === '#17171c' ? '#f5f5f7' : theme.backgroundColor,
    textColor: theme.textColor.toLowerCase() === '#f7f7f8' ? '#17171c' : theme.textColor,
    destinationColor: theme.destinationColor.toLowerCase() === '#ffffff' ? '#17171c' : theme.destinationColor,
    destinationTextColor:
      theme.destinationTextColor.toLowerCase() === '#17171c' ? '#ffffff' : theme.destinationTextColor,
  };
});

const fontClass = computed(
  () =>
    ({
      sans: 'font-sans',
      serif: 'font-serif',
      rounded: 'font-sans tracking-wide',
      mono: 'font-mono',
    })[props.page.theme.fontFamily] ?? 'font-sans',
);

const pageStyle = computed(() => {
  const theme = effectiveTheme.value;
  let background = theme.backgroundColor;

  if (theme.backgroundType === 'gradient') {
    background = `linear-gradient(145deg, ${theme.backgroundColor}, ${theme.gradientColor})`;
  }

  return {
    color: theme.textColor,
    background,
    backgroundImage:
      theme.backgroundType === 'image' && props.page.backgroundImageUrl
        ? `linear-gradient(rgb(0 0 0 / ${theme.overlayOpacity / 100}), rgb(0 0 0 / ${theme.overlayOpacity / 100})), url(${props.page.backgroundImageUrl})`
        : theme.backgroundType === 'gradient'
          ? background
          : undefined,
    backgroundSize: theme.imageFit,
    backgroundPosition: 'center',
    colorScheme: usesLightAppearance.value ? 'light' : 'dark',
  };
});

const profileShapeClass = computed(
  () => ({ circle: 'rounded-full', rounded: 'rounded-2xl', square: 'rounded-none' })[props.page.theme.profileShape],
);

const destinationRadiusClass = computed(
  () =>
    ({
      square: 'rounded-none',
      rounded: 'rounded-md',
      large: 'rounded-xl',
      pill: 'rounded-full',
    })[props.page.theme.destinationRadius],
);

const initials = computed(
  () =>
    props.page.displayName
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || '?',
);

const socialMarks: Record<string, string> = {
  facebook: 'f',
  github: 'GH',
  instagram: 'IG',
  linkedin: 'in',
  tiktok: 'TT',
  twitch: 'TV',
  twitter: 'X',
  x: 'X',
  youtube: 'YT',
  website: '↗',
};

const visibleElements = computed(() => props.page.elements.filter((element) => element.visible));
const socialIconsElements = computed(() =>
  visibleElements.value.filter((element) => element.type === 'social' && element.presentation === 'icon'),
);
const contentElements = computed(() =>
  visibleElements.value.filter((element) => element.type !== 'social' || element.presentation !== 'icon'),
);

function destinationHref(element: BioElement): string | undefined {
  if (!props.interactive) return undefined;
  if (element.url.startsWith('/') || /^https?:\/\//i.test(element.url)) return element.url || undefined;
  if (element.sourceType === 'email') return `mailto:${element.url}`;
  if (element.sourceType === 'telephone') return `tel:${element.url}`;
  return element.url || undefined;
}

function destinationStyle(element: BioElement) {
  const theme = effectiveTheme.value;
  const base = {
    color: theme.destinationTextColor,
    borderColor: theme.destinationColor,
  };

  if (theme.destinationStyle === 'outline') return { ...base, backgroundColor: 'transparent' };
  if (theme.destinationStyle === 'transparent')
    return { ...base, backgroundColor: 'transparent', borderColor: 'transparent' };
  if (theme.destinationStyle === 'soft') return { ...base, backgroundColor: `${theme.destinationColor}cc` };
  return { ...base, backgroundColor: theme.destinationColor };
}

async function share() {
  if (!props.interactive || !props.bioUrl) return;

  if (navigator.share) {
    await navigator.share({ title: props.page.shareTitle || props.page.displayName, url: props.bioUrl });
    return;
  }

  await navigator.clipboard.writeText(props.bioUrl);
}
</script>

<template>
  <article
    class="relative isolate mx-auto flex min-h-full w-full flex-col overflow-hidden"
    :class="[fontClass, compact ? 'px-5 py-7' : 'px-6 py-10 sm:px-8']"
    :style="pageStyle"
  >
    <button
      v-if="bioUrl"
      type="button"
      class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-black/20 text-current backdrop-blur transition hover:bg-black/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
      :disabled="!interactive"
      aria-label="Share this Bio Page"
      @click="share"
    >
      <Share2 class="h-4 w-4" />
    </button>

    <header class="mx-auto flex w-full max-w-lg flex-col items-center text-center">
      <img
        v-if="page.profileImageUrl"
        :src="page.profileImageUrl"
        alt=""
        class="h-24 w-24 border-2 border-white/20 object-cover shadow-lg"
        :class="profileShapeClass"
      />
      <span
        v-else
        aria-hidden="true"
        class="border-current/10 grid h-24 w-24 place-items-center border-2 bg-white/15 text-2xl font-semibold shadow-lg"
        :class="profileShapeClass"
      >
        {{ initials }}
      </span>
      <h1 class="mt-4 text-xl font-bold leading-tight">{{ page.displayName || 'Your name' }}</h1>
      <p v-if="page.publicHandle" class="mt-1 text-sm opacity-75">{{ page.publicHandle }}</p>
      <p v-if="page.biography" class="mt-3 max-w-md whitespace-pre-line text-sm leading-relaxed opacity-90">
        {{ page.biography }}
      </p>

      <div v-if="socialIconsElements.length" class="mt-5 flex flex-wrap justify-center gap-2">
        <component
          :is="interactive ? 'a' : 'span'"
          v-for="element in socialIconsElements"
          :key="element.id ?? element.clientId"
          :href="destinationHref(element)"
          :target="element.openInNewTab ? '_blank' : undefined"
          :rel="element.openInNewTab ? 'noopener noreferrer' : undefined"
          :aria-label="`${element.label}${element.openInNewTab ? ' (opens in a new tab)' : ''}`"
          class="grid h-10 w-10 place-items-center rounded-full bg-white/15 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
        >
          <span class="text-xs font-extrabold" aria-hidden="true">{{ socialMarks[element.socialService] ?? '↗' }}</span>
        </component>
      </div>
    </header>

    <main class="mx-auto mt-7 grid w-full max-w-lg gap-3">
      <template v-for="element in contentElements" :key="element.id ?? element.clientId">
        <h2 v-if="element.type === 'heading'" class="mb-0.5 mt-4 px-1 text-sm font-bold uppercase tracking-wider">
          {{ element.text }}
        </h2>
        <p v-else-if="element.type === 'text'" class="whitespace-pre-line px-1 text-sm leading-relaxed opacity-90">
          {{ element.text }}
        </p>
        <component
          :is="interactive ? 'a' : 'span'"
          v-else
          :href="destinationHref(element)"
          :target="element.openInNewTab ? '_blank' : undefined"
          :rel="element.openInNewTab ? 'noopener noreferrer' : undefined"
          :aria-label="`${element.label}${element.openInNewTab ? ' (opens in a new tab)' : ''}`"
          class="flex min-h-12 items-center justify-center gap-2 border px-4 py-3 text-center text-sm font-semibold transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
          :class="[destinationRadiusClass, page.theme.destinationShadow ? 'shadow-lg' : 'shadow-none']"
          :style="destinationStyle(element)"
        >
          <component
            :is="element.sourceType === 'email' ? Mail : element.sourceType === 'telephone' ? Phone : Globe2"
            class="h-4 w-4 shrink-0"
          />
          <span>{{ element.label || 'Untitled destination' }}</span>
        </component>
      </template>
    </main>

    <footer v-if="page.showBranding" class="mx-auto mt-auto pt-10 text-center text-[11px] font-medium opacity-60">
      Powered by Openlink
    </footer>
  </article>
</template>
