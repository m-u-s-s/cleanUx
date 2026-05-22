<template>
  <div
    class="fixed bottom-0 left-0 right-0 z-30 rounded-t-[28px] border-t px-5 pb-7 pt-3.5"
    style="
      background: linear-gradient(180deg, rgba(20,20,30,0.95) 0%, var(--color-bg) 100%);
      backdrop-filter: blur(30px);
      -webkit-backdrop-filter: blur(30px);
      border-color: var(--color-border);
      box-shadow: 0 -12px 40px rgba(0,0,0,0.6);
    "
  >
    <div data-test="sheet-handle" class="mx-auto mb-3.5 h-1 w-9 rounded-full" style="background: rgba(255,255,255,0.2);" />
    <slot />
  </div>
</template>
