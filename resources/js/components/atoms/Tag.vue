<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  variant?: 'primary' | 'urgent' | 'success' | 'neutral';
}

const props = withDefaults(defineProps<Props>(), { variant: 'primary' });

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'urgent': return 'bg-semantic-urgent/10 text-semantic-urgent';
    case 'success': return 'bg-semantic-success/10 text-semantic-success';
    case 'neutral': return 'bg-semantic-text-3/10 text-semantic-text-2';
    default: return 'bg-semantic-primary/10 text-semantic-primary';
  }
});
</script>

<template>
  <span
    :class="[
      'inline-block rounded-md px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider',
      variantClasses,
    ]"
  >
    <slot />
  </span>
</template>
