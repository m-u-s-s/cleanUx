<script setup lang="ts">
interface Props {
  title: string;
  subtitle: string;
  disabled?: boolean;
}

withDefaults(defineProps<Props>(), { disabled: false });
defineEmits<{ scan: [] }>();
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    class="flex w-full items-center gap-3.5 rounded-[18px] p-4 text-white transition-transform active:scale-95 disabled:opacity-50"
    style="background: linear-gradient(180deg, var(--color-primary), var(--color-primary-hover)); box-shadow: var(--shadow-cta);"
    @click="$emit('scan')"
  >
    <span class="flex h-12 w-12 items-center justify-center rounded-xl text-2xl" style="background: rgba(255,255,255,0.15);">
      ▦
    </span>
    <span class="flex-1 text-left">
      <span class="block text-[15px] font-bold">{{ title }}</span>
      <span class="mt-0.5 block text-[11px] opacity-85">{{ subtitle }}</span>
    </span>
    <span class="text-xl opacity-70">›</span>
  </button>
</template>
