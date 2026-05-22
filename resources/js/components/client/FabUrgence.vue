<script setup lang="ts">
defineEmits<{ trigger: [] }>();
</script>

<template>
  <button
    type="button"
    aria-label="Demander urgent"
    class="fixed bottom-[110px] right-[18px] z-40 flex h-14 w-14 items-center justify-center rounded-full text-white text-2xl transition-transform active:scale-90"
    style="background: linear-gradient(180deg, var(--color-urgent), var(--color-urgent-hover)); box-shadow: var(--shadow-fab);"
    @click="$emit('trigger')"
  >
    ⚡
  </button>
</template>
