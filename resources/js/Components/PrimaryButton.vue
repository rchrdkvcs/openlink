<template>
  <button
    class="inline-flex h-9 select-none items-center justify-center gap-2 rounded-md bg-foreground px-3.5 text-sm font-medium text-background transition-colors duration-150 hover:bg-foreground/85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 disabled:pointer-events-none disabled:opacity-50"
  >
    <slot />
  </button>
</template>
