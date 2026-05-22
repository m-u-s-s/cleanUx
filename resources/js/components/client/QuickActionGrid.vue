<script setup lang="ts">
interface Action {
  id: string;
  icon: string;
  label: string;
}

interface Props {
  actions: Action[];
}

defineProps<Props>();
defineEmits<{ action: [id: string] }>();
</script>

<template>
  <div class="grid grid-cols-4 gap-2">
    <button
      v-for="action in actions"
      :key="action.id"
      data-test="quick-action"
      type="button"
      class="flex flex-col items-center gap-1.5 rounded-[18px] border p-3 transition-transform active:scale-95"
      style="background: var(--color-surface); border-color: var(--color-border);"
      @click="$emit('action', action.id)"
    >
      <span class="text-[22px]">{{ action.icon }}</span>
      <span class="text-[10px] font-semibold leading-tight text-center" style="color: var(--color-text);">
        {{ action.label }}
      </span>
    </button>
  </div>
</template>
