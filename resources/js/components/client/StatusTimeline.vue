<script setup lang="ts">
import { computed } from 'vue';

interface Step {
  id: string;
  label: string;
}

interface Props {
  steps: Step[];
  currentId: string;
}

const props = defineProps<Props>();

const currentIndex = computed(() => props.steps.findIndex(s => s.id === props.currentId));
const stateOf = (idx: number) => {
  if (idx < currentIndex.value) return 'done';
  if (idx === currentIndex.value) return 'active';
  return 'pending';
};
</script>

<template>
  <div class="relative flex items-center justify-between">
    <div
      class="absolute left-2.5 right-2.5 top-[9px] h-0.5"
      style="background: rgba(255, 255, 255, 0.08);"
    />
    <div
      v-for="(step, idx) in steps"
      :key="step.id"
      data-test="step"
      class="relative z-10 flex flex-col items-center gap-1"
    >
      <span
        data-test="step-dot"
        :class="[
          'block h-[18px] w-[18px] rounded-full border-2',
          stateOf(idx) === 'done' ? 'is-done' : '',
          stateOf(idx) === 'active' ? 'is-active' : '',
        ]"
        :style="{
          borderColor: 'var(--color-bg)',
          background: stateOf(idx) === 'done' ? 'var(--color-success)' :
                      stateOf(idx) === 'active' ? 'var(--color-primary)' :
                      'rgba(255,255,255,0.1)',
          boxShadow: stateOf(idx) === 'active' ? '0 0 0 4px rgba(129,140,248,0.2)' : 'none',
        }"
      />
      <span
        class="text-[9px] font-semibold"
        :style="{
          color: stateOf(idx) === 'done' ? 'var(--color-success)' :
                 stateOf(idx) === 'active' ? 'var(--color-primary)' :
                 'var(--color-text-3)',
        }"
      >
        {{ step.label }}
      </span>
    </div>
  </div>
</template>
