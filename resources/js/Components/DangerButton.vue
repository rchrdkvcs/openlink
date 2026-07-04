<template>
    <button
        class="inline-flex h-9 select-none items-center justify-center gap-2 rounded-md border border-danger/30 bg-danger/10 px-3.5 text-sm font-medium text-danger transition-colors duration-150 hover:border-danger/50 hover:bg-danger/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger/40 disabled:pointer-events-none disabled:opacity-50"
    >
        <slot />
    </button>
</template>
