<script setup lang="ts">
import StatusTimeline from './StatusTimeline.vue';

interface Step {
  id: string;
  label: string;
}

interface Props {
  etaMinutes: number;
  distanceKm: number;
  providerName: string;
  steps: Step[];
  currentStep: string;
}

defineProps<Props>();
defineEmits<{ dismiss: [] }>();
</script>

<template>
  <div
    class="rounded-[22px] border p-4"
    style="
      background: linear-gradient(180deg, rgba(20,20,30,0.92), rgba(14,14,24,0.92));
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-color: rgba(255,255,255,0.08);
      box-shadow: 0 12px 32px rgba(0,0,0,0.4);
    "
  >
    <div class="flex items-start justify-between">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-primary);">
          Arrivée dans
        </p>
        <p class="mt-1 text-[32px] font-extrabold leading-none tracking-tight" style="color: var(--color-text);">
          {{ etaMinutes }} <span class="text-lg opacity-60">min</span>
        </p>
        <p class="mt-0.5 text-xs" style="color: var(--color-text-2);">
          {{ distanceKm }} km · {{ providerName }} en route
        </p>
      </div>
      <button
        type="button"
        aria-label="Fermer"
        class="flex h-10 w-10 items-center justify-center rounded-xl border text-lg"
        style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.1); color: var(--color-text);"
        @click="$emit('dismiss')"
      >
        ✕
      </button>
    </div>
    <div class="mt-3.5 border-t pt-3.5" style="border-color: rgba(255,255,255,0.06);">
      <StatusTimeline :steps="steps" :current-id="currentStep" />
    </div>
  </div>
</template>
