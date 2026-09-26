<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
withDefaults(defineProps<{ type?: 'button' | 'submit' | 'reset' }>(), { type: 'submit' });
</script>
<template>
  <Button variant="primary" :type="type"><slot /></Button>
</template>
