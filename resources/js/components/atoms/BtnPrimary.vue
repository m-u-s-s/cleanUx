<script setup lang="ts">
interface Props {
  disabled?: boolean;
  fullWidth?: boolean;
}

withDefaults(defineProps<Props>(), { disabled: false, fullWidth: false });
defineEmits<{ click: [event: MouseEvent] }>();
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    :class="[
      'rounded-2xl px-4 py-3 text-sm font-bold text-white transition-all',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'shadow-cta',
      fullWidth ? 'w-full' : '',
    ]"
    style="background: linear-gradient(180deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);"
    @click="$emit('click', $event)"
  >
    <slot />
  </button>
</template>
