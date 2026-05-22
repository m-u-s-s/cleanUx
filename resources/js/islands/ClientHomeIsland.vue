<script setup lang="ts">
import { useAdaptiveTheme, type Theme } from '@/composables/useAdaptiveTheme';
import Avatar from '@/components/atoms/Avatar.vue';
import AdaptiveHero from '@/components/client/AdaptiveHero.vue';
import QuickActionGrid from '@/components/client/QuickActionGrid.vue';
import ServiceTile from '@/components/client/ServiceTile.vue';
import StatusCardScroller from '@/components/client/StatusCardScroller.vue';
import FabUrgence from '@/components/client/FabUrgence.vue';
import BottomNav from '@/components/client/BottomNav.vue';

interface UpcomingBooking {
  title: string;
  meta: string;
  tags?: string[];
}

interface StatusCard {
  id: string;
  label: string;
  value: string;
  sub?: string;
  badge?: string;
}

interface Service {
  emoji: string;
  name: string;
}

interface Props {
  userName: string;
  initialTheme: Theme;
  upcomingBooking: UpcomingBooking | null;
  statusCards: StatusCard[];
  services: Service[];
}

const props = defineProps<Props>();

useAdaptiveTheme(props.initialTheme);

const quickActions = [
  { id: 'urgent', icon: '⚡', label: 'Urgent' },
  { id: 'rebook', icon: '🔁', label: 'Rebook' },
  { id: 'address', icon: '📍', label: 'Adresses' },
  { id: 'help', icon: '💬', label: 'Aide' },
];

const navItems = [
  { id: 'home', icon: '⌂', label: 'Accueil' },
  { id: 'search', icon: '⌕', label: 'Recherche' },
  { id: 'bookings', icon: '▦', label: 'RDV' },
  { id: 'alerts', icon: '○', label: 'Alertes' },
  { id: 'profile', icon: '⊙', label: 'Profil' },
];

const dispatchClientAction = (id: string, payload: Record<string, unknown> = {}) => {
  window.dispatchEvent(
    new CustomEvent('cleanux:client-action', {
      detail: { id, ...payload },
    })
  );
};
</script>

<template>
  <div class="relative min-h-screen pb-[120px]" style="background: var(--color-bg); color: var(--color-text);">
    <header class="flex items-center justify-between px-5 pt-2">
      <div>
        <p class="text-[13px]" style="color: var(--color-text-2);">Bonjour,</p>
        <h1 class="text-[26px] font-extrabold leading-none tracking-tight">{{ userName }}</h1>
      </div>
      <Avatar :name="userName" size="md" />
    </header>

    <section v-if="upcomingBooking" class="mt-5 px-5">
      <AdaptiveHero
        eyebrow="Prochain rendez-vous"
        :title="upcomingBooking.title"
        :meta="upcomingBooking.meta"
        :tags="upcomingBooking.tags || []"
        @primary-action="dispatchClientAction('open-booking-detail')"
        @secondary-action="dispatchClientAction('open-tracking')"
      />
    </section>

    <section class="mt-4 px-5">
      <QuickActionGrid :actions="quickActions" @action="(id) => dispatchClientAction(id)" />
    </section>

    <section v-if="statusCards.length" class="mt-5">
      <div class="flex items-baseline justify-between px-5 pb-2">
        <h2 class="text-[13px] font-bold tracking-tight" style="color: var(--color-text);">Mon statut</h2>
        <button type="button" class="text-[11px] font-semibold" style="color: var(--color-primary);" @click="dispatchClientAction('open-status-detail')">
          Voir tout →
        </button>
      </div>
      <div class="px-5">
        <StatusCardScroller :cards="statusCards" @select="(id) => dispatchClientAction('open-status', { kind: id })" />
      </div>
    </section>

    <section v-if="services.length" class="mt-5">
      <div class="flex items-baseline justify-between px-5 pb-2">
        <h2 class="text-[13px] font-bold tracking-tight" style="color: var(--color-text);">Services près de chez toi</h2>
        <button type="button" class="text-[11px] font-semibold" style="color: var(--color-primary);" @click="dispatchClientAction('open-browse')">
          Tous →
        </button>
      </div>
      <div class="grid grid-cols-3 gap-2 px-5">
        <ServiceTile
          v-for="service in services"
          :key="service.name"
          :emoji="service.emoji"
          :name="service.name"
          @select="(name) => dispatchClientAction('start-booking', { service: name })"
        />
      </div>
    </section>

    <FabUrgence @trigger="dispatchClientAction('start-urgent')" />

    <BottomNav :items="navItems" active-id="home" @navigate="(id) => dispatchClientAction('navigate', { route: id })" />
  </div>
</template>
