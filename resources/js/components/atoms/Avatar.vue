<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  name: string;
  size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
});

const initial = computed(() => props.name.charAt(0).toUpperCase());

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'h-8 w-8 text-xs';
    case 'lg': return 'h-12 w-12 text-base';
    default: return 'h-11 w-11 text-sm';
  }
});
</script>

<template>
  <div
    :class="[
      'inline-flex items-center justify-center rounded-full font-bold text-white',
      sizeClasses,
    ]"
    style="background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-primary) 100%); box-shadow: 0 4px 12px rgba(99,102,241,0.25);"
  >
    {{ initial }}
  </div>
</template>
