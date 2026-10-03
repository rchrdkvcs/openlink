<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import type { Pagination } from '@/types/payloads';

defineProps<{ pagination: Pagination }>();

const emit = defineEmits<{ page: [page: number] }>();
</script>

<template>
  <nav
    v-if="pagination.lastPage > 1"
    class="mt-6 flex items-center justify-between gap-3 text-[13px]"
    aria-label="Pagination"
  >
    <span class="text-faint">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span>
    <div class="flex gap-2">
      <Button
        variant="secondary"
        size="sm"
        type="button"
        :disabled="pagination.currentPage <= 1"
        @click="emit('page', pagination.currentPage - 1)"
        >Previous</Button
      >
      <Button
        variant="secondary"
        size="sm"
        type="button"
        :disabled="pagination.currentPage >= pagination.lastPage"
        @click="emit('page', pagination.currentPage + 1)"
        >Next</Button
      >
    </div>
  </nav>
</template>
