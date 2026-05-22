<script setup lang="ts">
interface Props {
  disabled?: boolean;
}

withDefaults(defineProps<Props>(), { disabled: false });
defineEmits<{ click: [event: MouseEvent] }>();
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    class="rounded-2xl px-4 py-3 text-sm font-bold transition-all disabled:opacity-50"
    style="background: rgba(99,102,241,0.06); color: var(--color-primary);"
    @click="$emit('click', $event)"
  >
    <slot />
  </button>
</template>
