<script setup lang="ts">
interface Props {
  emoji: string;
  name: string;
}

const props = defineProps<Props>();
defineEmits<{ select: [name: string] }>();
</script>

<template>
  <button
    type="button"
    class="flex flex-col items-center gap-1.5 rounded-2xl border p-3.5 transition-transform active:scale-95"
    style="background: var(--color-surface); border-color: var(--color-border);"
    @click="$emit('select', props.name)"
  >
    <span class="text-2xl">{{ emoji }}</span>
    <span class="text-[10px] font-semibold" style="color: var(--color-text);">{{ name }}</span>
  </button>
</template>
