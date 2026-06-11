<script setup>
import { RouterLink } from 'vue-router';
import AppIcon from '../../../shared/icons/AppIcon.vue';
import Pill from './Pill.vue';

defineProps({
  to: { type: [Object, String], required: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' }, // owning org / customer name
  description: { type: String, default: '' },
  version: { type: String, default: '' },
  beta: { type: Boolean, default: false },
  updated: { type: String, default: '' }, // relative time string
});
</script>

<template>
  <RouterLink :to="to" class="group flex items-start gap-4 py-6 border-b border-field-border hover:bg-panel/60 px-2 -mx-2 rounded">
    <div class="flex-1 min-w-0">
      <div v-if="subtitle" class="flex items-center gap-2 text-meta text-[13px] mb-1">
        <AppIcon name="briefcase" :size="14" />
        <span class="truncate">{{ subtitle }}</span>
      </div>
      <h3 class="text-[15px] font-semibold text-black mb-1">{{ title }}</h3>
      <p v-if="description" class="text-[14px] text-muted line-clamp-2 mb-3">{{ description }}</p>
      <div class="flex items-center gap-2">
        <Pill v-if="version" :label="version" variant="version" />
        <Pill v-if="beta" label="BETA" variant="beta" />
      </div>
    </div>
    <div class="flex items-center gap-4 shrink-0 pt-1">
      <span v-if="updated" class="text-meta text-[13px] whitespace-nowrap">{{ updated }}</span>
      <AppIcon name="chevron-right" :size="18" class="text-label-inactive group-hover:text-meta" />
    </div>
  </RouterLink>
</template>
