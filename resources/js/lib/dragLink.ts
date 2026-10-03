import { ref } from 'vue';

export type DraggedLink = { id: number; folderId: number | null };

export const draggedLink = ref<DraggedLink | null>(null);
