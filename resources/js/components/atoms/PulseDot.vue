<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  variant?: 'urgent' | 'success' | 'primary';
}

const props = withDefaults(defineProps<Props>(), { variant: 'urgent' });

const variantClass = computed(() => {
  switch (props.variant) {
    case 'success': return 'bg-semantic-success';
    case 'primary': return 'bg-semantic-primary';
    default: return 'bg-semantic-urgent';
  }
});
</script>

<template>
  <span :class="['inline-block h-2 w-2 rounded-full animate-pulse-glow', variantClass]" />
</template>

<style>
@keyframes pulse-glow {
  0% { box-shadow: 0 0 0 0 currentColor; opacity: 1; }
  70% { box-shadow: 0 0 0 12px transparent; opacity: 0.85; }
  100% { box-shadow: 0 0 0 0 transparent; opacity: 1; }
}
.animate-pulse-glow {
  animation: pulse-glow 1.6s infinite;
}
</style>
