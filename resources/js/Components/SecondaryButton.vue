<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
withDefaults(defineProps<{ type?: 'button' | 'submit' | 'reset' }>(), { type: 'button' });
</script>
<template>
  <Button variant="secondary" :type="type"><slot /></Button>
</template>
