<script setup>
defineProps({
  projectName: { type: String, default: '' },
  // When true, hides the header divider + label/button slots — used for the
  // empty state (no release notes yet), where only the project name shows.
  hideHeaderExtras: { type: Boolean, default: false },
});
</script>

<template>
  <div class="min-h-screen bg-page flex items-start justify-center p-6 sm:py-16">
    <div class="w-full max-w-[750px] bg-card border border-card-border rounded-card shadow-card overflow-hidden">
      <!-- Shared header row -->
      <header class="flex items-center justify-between gap-3 px-6 sm:px-12 py-4 border-b border-card-border">
        <div class="flex items-center gap-3 min-w-0">
          <h1 class="text-[20px] leading-[22px] font-semibold uppercase text-title truncate">
            {{ projectName }}
          </h1>
          <template v-if="!hideHeaderExtras">
            <span class="h-5 w-px bg-card-border shrink-0" aria-hidden="true"></span>
            <slot name="label" />
          </template>
        </div>
        <div v-if="!hideHeaderExtras" class="shrink-0">
          <slot name="button" />
        </div>
      </header>

      <!-- Body (face content) -->
      <div class="px-6 sm:pl-[70px] sm:pr-24 pt-6 sm:pt-12">
        <slot />
      </div>
    </div>
  </div>
</template>
