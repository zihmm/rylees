<script setup>
defineProps({
  projectName: { type: String, default: '' },
});
</script>

<template>
  <div class="min-h-screen bg-page flex items-start justify-center p-6 sm:py-16">
    <div class="w-full max-w-[750px] bg-card border border-card-border rounded-card shadow-card overflow-hidden">
      <!-- Shared header row -->
      <header class="flex items-center justify-between gap-3 px-6 py-4 border-b border-card-border">
        <div class="flex items-center gap-3 min-w-0">
          <h1 class="text-[20px] leading-[22px] font-semibold uppercase text-title truncate">
            {{ projectName }}
          </h1>
          <span class="h-5 w-px bg-card-border shrink-0" aria-hidden="true"></span>
          <slot name="label" />
        </div>
        <div class="shrink-0">
          <slot name="button" />
        </div>
      </header>

      <!-- Body (face content) -->
      <div class="px-6 py-6">
        <slot />
      </div>
    </div>
  </div>
</template>
