<script setup lang="ts">
import BtnPrimary from '@/components/atoms/BtnPrimary.vue';
import BtnSecondary from '@/components/atoms/BtnSecondary.vue';
import Tag from '@/components/atoms/Tag.vue';

interface Props {
  eyebrow: string;
  title: string;
  meta?: string;
  tags?: string[];
  primaryLabel?: string;
  secondaryLabel?: string;
}

withDefaults(defineProps<Props>(), {
  meta: '',
  tags: () => [],
  primaryLabel: 'Voir le détail',
  secondaryLabel: 'Suivre',
});

defineEmits<{
  'primary-action': [];
  'secondary-action': [];
}>();
</script>

<template>
  <div
    class="relative overflow-hidden rounded-[28px] border p-6"
    style="
      background: linear-gradient(135deg, var(--color-surface) 0%, var(--color-surface-2) 100%);
      border-color: var(--color-border-2);
      box-shadow: var(--shadow-card);
    "
  >
    <div
      class="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full"
      style="background: radial-gradient(circle, rgba(167,139,250,0.18) 0%, transparent 70%);"
    />
    <div class="relative">
      <p class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-primary);">
        {{ eyebrow }}
      </p>
      <h2 class="mt-1.5 text-[22px] font-extrabold leading-tight tracking-tight" style="color: var(--color-text);">
        {{ title }}
      </h2>
      <p v-if="meta" class="mt-1 text-[13px]" style="color: var(--color-text-2);">{{ meta }}</p>
      <div v-if="tags.length" class="mt-3.5 flex gap-2">
        <Tag v-for="tag in tags" :key="tag" variant="primary">{{ tag }}</Tag>
      </div>
      <div class="mt-4 flex gap-2">
        <BtnPrimary class="flex-1" data-test="primary-action" @click="$emit('primary-action')">
          {{ primaryLabel }}
        </BtnPrimary>
        <BtnSecondary data-test="secondary-action" @click="$emit('secondary-action')">
          {{ secondaryLabel }}
        </BtnSecondary>
      </div>
    </div>
  </div>
</template>
