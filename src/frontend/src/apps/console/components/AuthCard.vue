<script setup>
import AppLogo from './AppLogo.vue';
</script>

<template>
  <div class="min-h-screen bg-page flex items-center justify-center p-6">
    <div class="w-full max-w-[479px] bg-white rounded-card shadow-card p-10">
      <slot name="top" />
      <div class="flex justify-center mb-8">
        <AppLogo :size="40" />
      </div>
      <slot />
    </div>
  </div>
</template>
