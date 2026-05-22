<script setup lang="ts">
import { computed } from 'vue';
import Avatar from '@/components/atoms/Avatar.vue';

type Theme = 'light' | 'dark' | 'auto';

interface Props {
  open: boolean;
  userName: string;
  userEmail: string;
  initialTheme: Theme;
  profileUrl: string;
  notificationsUrl: string;
  helpUrl: string;
  logoutUrl: string;
  csrfToken: string;
}

const props = defineProps<Props>();
defineEmits<{
  close: [];
  'theme-change': [theme: Theme];
}>();

const themeOptions: { value: Theme; label: string; icon: string }[] = [
  { value: 'light', label: 'Clair', icon: '☀' },
  { value: 'dark', label: 'Sombre', icon: '☾' },
  { value: 'auto', label: 'Auto', icon: '⊙' },
];

const initial = computed(() => props.initialTheme);
</script>

<template>
  <Teleport to="body">
    <div v-if="open" data-test="sheet-container" class="fixed inset-0 z-50">
      <div
        data-test="backdrop"
        class="absolute inset-0 bg-black/60"
        style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);"
        @click="$emit('close')"
      />
      <div
        class="absolute bottom-0 left-0 right-0 rounded-t-[28px] border-t pb-7 pt-3.5"
        style="
          background: var(--color-surface);
          border-color: var(--color-border);
          box-shadow: 0 -12px 40px rgba(0,0,0,0.4);
          padding-bottom: calc(env(safe-area-inset-bottom) + 28px);
        "
      >
        <div class="mx-auto mb-4 h-1 w-9 rounded-full" style="background: var(--color-border-2);" />

        <div class="flex items-center gap-3 px-5">
          <Avatar :name="userName" size="md" />
          <div class="flex-1">
            <p class="text-base font-bold tracking-tight" style="color: var(--color-text);">{{ userName }}</p>
            <p class="text-[11px]" style="color: var(--color-text-2);">{{ userEmail }}</p>
          </div>
        </div>

        <div class="mt-5 px-5">
          <p class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-3);">Thème</p>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="option in themeOptions"
              :key="option.value"
              type="button"
              :data-test="`theme-${option.value}`"
              :class="[
                'flex flex-col items-center gap-1 rounded-2xl border py-3 text-xs font-semibold transition-transform active:scale-95',
                initial === option.value ? 'ring-2' : '',
              ]"
              :style="{
                background: initial === option.value ? 'var(--color-surface-2)' : 'var(--color-surface)',
                borderColor: initial === option.value ? 'var(--color-primary)' : 'var(--color-border)',
                color: 'var(--color-text)',
              }"
              @click="$emit('theme-change', option.value)"
            >
              <span class="text-lg">{{ option.icon }}</span>
              <span>{{ option.label }}</span>
            </button>
          </div>
        </div>

        <div class="mt-5 px-5 space-y-1">
          <a
            :href="profileUrl"
            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold"
            style="color: var(--color-text);"
          >
            <span class="text-lg">👤</span>
            <span class="flex-1">Mon profil</span>
            <span class="opacity-50">›</span>
          </a>
          <a
            :href="notificationsUrl"
            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold"
            style="color: var(--color-text);"
          >
            <span class="text-lg">🔔</span>
            <span class="flex-1">Notifications</span>
            <span class="opacity-50">›</span>
          </a>
          <a
            :href="helpUrl"
            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold"
            style="color: var(--color-text);"
          >
            <span class="text-lg">❓</span>
            <span class="flex-1">Aide</span>
            <span class="opacity-50">›</span>
          </a>
        </div>

        <div class="mt-5 px-5">
          <form data-test="logout-form" :action="logoutUrl" method="POST">
            <input type="hidden" name="_token" :value="csrfToken" />
            <button
              type="submit"
              class="flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-bold text-white"
              style="background: var(--color-urgent);"
            >
              <span>🚪</span>
              <span>Déconnexion</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </Teleport>
</template>
