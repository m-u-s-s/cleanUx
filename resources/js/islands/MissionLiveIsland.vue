<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useAdaptiveTheme } from '@/composables/useAdaptiveTheme';
import { useReverbChannel } from '@/composables/useReverbChannel';
import Avatar from '@/components/atoms/Avatar.vue';
import PulseDot from '@/components/atoms/PulseDot.vue';
import EtaGlassCard from '@/components/client/EtaGlassCard.vue';
import BottomActionSheet from '@/components/client/BottomActionSheet.vue';
import QrScanCta from '@/components/client/QrScanCta.vue';

interface Provider {
  name: string;
  rating: number;
  missionsCount: number;
  label: string;
}

interface Props {
  missionId: number;
  initialEtaMinutes: number;
  initialDistanceKm: number;
  initialCurrentStep: string;
  provider: Provider;
  isUrgent: boolean;
}

const props = defineProps<Props>();

// Force dark mode on mount (active mission triggers it)
useAdaptiveTheme('dark');

const etaMinutes = ref(props.initialEtaMinutes);
const distanceKm = ref(props.initialDistanceKm);
const currentStep = ref(props.initialCurrentStep);

const steps = [
  { id: 'accepted', label: 'Accepté' },
  { id: 'enroute', label: 'En route' },
  { id: 'arrived', label: 'Arrivé' },
  { id: 'mission', label: 'Mission' },
  { id: 'done', label: 'Terminé' },
];

let reverbHandle: { unsubscribe: () => void } | null = null;

onMounted(() => {
  reverbHandle = useReverbChannel(`mission.${props.missionId}`, {
    MissionLivePosition: (payload: any) => {
      if (typeof payload?.distance_km === 'number') distanceKm.value = payload.distance_km;
      if (typeof payload?.eta_minutes === 'number') etaMinutes.value = payload.eta_minutes;
    },
    MissionLiveEta: (payload: any) => {
      if (typeof payload?.eta_minutes === 'number') etaMinutes.value = payload.eta_minutes;
    },
    MissionStatusUpdated: (payload: any) => {
      if (typeof payload?.step === 'string') currentStep.value = payload.step;
    },
  });
});

onBeforeUnmount(() => {
  reverbHandle?.unsubscribe();
});

const onScan = () => {
  window.dispatchEvent(
    new CustomEvent('cleanux:mission-scan', {
      detail: { missionId: props.missionId, step: currentStep.value },
    })
  );
};

const onCall = () => {
  window.dispatchEvent(
    new CustomEvent('cleanux:mission-call', {
      detail: { missionId: props.missionId },
    })
  );
};

const scanCtaTitle = () => {
  if (currentStep.value === 'arrived') return 'Scanner le QR pour démarrer';
  if (currentStep.value === 'mission') return 'Scanner pour terminer';
  return "En attente de l'arrivée…";
};
const scanCtaSub = () =>
  currentStep.value === 'arrived' || currentStep.value === 'mission'
    ? 'Code 6 chiffres'
    : "QR disponible à l'arrivée";
const scanDisabled = () =>
  !['arrived', 'mission'].includes(currentStep.value);
</script>

<template>
  <div class="relative min-h-screen overflow-hidden" style="background: var(--color-bg); color: var(--color-text);">
    <div
      class="absolute inset-0"
      style="
        background:
          radial-gradient(circle at 30% 40%, rgba(99,102,241,0.15) 0%, transparent 50%),
          radial-gradient(circle at 70% 60%, rgba(167,139,250,0.1) 0%, transparent 50%),
          linear-gradient(180deg, var(--color-bg) 0%, var(--color-surface-2) 100%);
      "
    />

    <div
      v-if="isUrgent"
      class="absolute left-3 right-3 top-14 z-10 flex items-center gap-2 rounded-xl border px-3 py-1.5 text-[11px] font-bold backdrop-blur"
      style="background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.3); color: #fca5a5;"
    >
      <PulseDot variant="urgent" />
      <span>URGENCE · MISSION EN COURS</span>
    </div>

    <div class="absolute left-5 right-5 top-[100px] z-10">
      <EtaGlassCard
        :eta-minutes="etaMinutes"
        :distance-km="distanceKm"
        :provider-name="provider.name"
        :steps="steps"
        :current-step="currentStep"
      />
    </div>

    <BottomActionSheet>
      <div class="mb-4 flex items-center gap-3">
        <Avatar :name="provider.name" size="md" />
        <div class="flex-1">
          <p class="text-base font-bold tracking-tight">{{ provider.name }}</p>
          <p class="mt-0.5 text-[11px]" style="color: var(--color-text-2);">
            <span style="color: var(--color-warning); font-weight: 600;">★ {{ provider.rating }}</span>
            · {{ provider.missionsCount }} missions · {{ provider.label }}
          </p>
        </div>
        <button
          type="button"
          aria-label="Appeler"
          class="flex h-11 w-11 items-center justify-center rounded-full text-lg text-white"
          style="background: linear-gradient(180deg, var(--color-success), #059669); box-shadow: var(--shadow-call);"
          @click="onCall"
        >
          📞
        </button>
      </div>

      <QrScanCta
        :title="scanCtaTitle()"
        :subtitle="scanCtaSub()"
        :disabled="scanDisabled()"
        @scan="onScan"
      />
    </BottomActionSheet>
  </div>
</template>
