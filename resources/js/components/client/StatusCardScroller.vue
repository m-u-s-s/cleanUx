<script setup lang="ts">
interface StatusCard {
  id: string;
  label: string;
  value: string;
  sub?: string;
  badge?: string;
}

interface Props {
  cards: StatusCard[];
}

defineProps<Props>();
defineEmits<{ select: [id: string] }>();
</script>

<template>
  <div class="flex gap-2.5 overflow-x-auto pb-1">
    <button
      v-for="card in cards"
      :key="card.id"
      data-test="status-card"
      type="button"
      class="min-w-[110px] flex-1 rounded-2xl border p-3 text-left transition-transform active:scale-95"
      style="background: var(--color-surface); border-color: var(--color-border);"
      @click="$emit('select', card.id)"
    >
      <p class="text-[9px] font-bold uppercase tracking-wider" style="color: var(--color-text-3);">
        {{ card.label }}
      </p>
      <p class="mt-1 text-lg font-extrabold tracking-tight" style="color: var(--color-text);">
        {{ card.value }}
        <span v-if="card.badge" class="ml-1 inline-block rounded px-1.5 py-0.5 text-[8px] font-bold" style="background: linear-gradient(180deg,#e8e8ee,#d4d4dc); color:#4a4a55;">
          {{ card.badge }}
        </span>
      </p>
      <p v-if="card.sub" class="mt-0.5 text-[10px]" style="color: var(--color-text-2);">{{ card.sub }}</p>
    </button>
  </div>
</template>
