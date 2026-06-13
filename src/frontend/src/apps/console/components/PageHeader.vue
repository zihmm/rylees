<script setup>
import { RouterLink } from 'vue-router';
import AppIcon from '../../../shared/icons/AppIcon.vue';

defineProps({
  // Optional parent crumb: { label, to }
  parent: { type: Object, default: null },
  current: { type: String, default: '' },
});
</script>

<template>
  <header class="px-10 h-[70px] flex items-center justify-between gap-4 border-b border-field-border">
    <h1 class="flex items-center gap-3 text-[17px] font-medium">
      <template v-if="parent">
        <RouterLink :to="parent.to" class="text-label-inactive hover:text-meta">{{ parent.label }}</RouterLink>
        <AppIcon name="chevron-right" :size="14" class="text-label-inactive" />
      </template>
      <span class="text-black">{{ current }}</span>
    </h1>
    <div class="shrink-0">
      <slot name="actions" />
    </div>
  </header>
</template>
