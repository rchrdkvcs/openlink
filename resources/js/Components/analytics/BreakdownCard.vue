<script setup lang="ts">
import BarList from '@/Components/analytics/BarList.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import { type BreakdownTab } from '@/lib/analytics';
import { ref } from 'vue';

const props = defineProps<{
    title: string;
    tabs: BreakdownTab[];
}>();

const activeKey = ref(props.tabs[0]?.key);
</script>

<template>
    <SectionCard :title="title">
        <template #header>
            <div v-if="tabs.length > 1" class="flex items-center gap-0.5 rounded-md bg-elevated p-0.5">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-[5px] px-2 py-1 text-xs font-medium transition-colors duration-100"
                    :class="activeKey === tab.key ? 'bg-surface text-foreground shadow-sm' : 'text-muted hover:text-foreground'"
                    @click="activeKey = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </template>

        <template v-for="tab in tabs" :key="tab.key">
            <BarList v-if="activeKey === tab.key" :rows="tab.rows" :empty="tab.empty" />
        </template>
    </SectionCard>
</template>
