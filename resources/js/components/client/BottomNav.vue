<script setup lang="ts">
interface NavItem {
  id: string;
  icon: string;
  label: string;
}

interface Props {
  items: NavItem[];
  activeId: string;
}

defineProps<Props>();
defineEmits<{ navigate: [id: string] }>();
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-30 grid grid-cols-5 gap-1 border-t px-2 pb-7 pt-3"
    style="
      background: color-mix(in srgb, var(--color-surface) 86%, transparent);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-color: var(--color-border);
    "
  >
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      data-test="nav-item"
      :class="['flex flex-col items-center gap-0.5 p-1', activeId === item.id ? 'is-active' : '']"
      @click="$emit('navigate', item.id)"
    >
      <span class="text-[22px]" :style="{ opacity: activeId === item.id ? 1 : 0.45 }">{{ item.icon }}</span>
      <span class="text-[9px] font-semibold" :style="{ color: activeId === item.id ? 'var(--color-primary)' : 'var(--color-text-3)' }">
        {{ item.label }}
      </span>
    </button>
  </nav>
</template>
