<script setup>
import { RouterLink } from 'vue-router';
import AppIcon from '../../../shared/icons/AppIcon.vue';
import Pill from './Pill.vue';

defineProps({
  to: { type: [Object, String], default: null }, // omit for a non-clickable card
  title: { type: String, required: true },
  subtitle: { type: String, default: '' }, // owning org / customer / contact name
  subtitleTo: { type: [Object, String], default: null }, // makes the subtitle its own link
  subtitleIcon: { type: String, default: 'briefcase' },
  active: { type: Boolean, default: true }, // green dot when active, grey otherwise
  description: { type: String, default: '' },
  version: { type: String, default: '' },
  beta: { type: Boolean, default: false },
  updated: { type: String, default: '' }, // relative time string
});
</script>

<template>
  <component
    :is="to ? RouterLink : 'div'"
    :to="to || undefined"
    class="group flex items-start gap-4 py-6 border-b border-field-border px-2 -mx-2 rounded"
    :class="to ? 'hover:bg-panel/60' : ''"
  >
    <!-- Active-state dot: solid inner circle with a lighter outer halo ring. -->
    <span
      class="mt-1 flex items-center justify-center w-[15px] h-[15px] rounded-full shrink-0"
      :class="active ? 'bg-green-500/25' : 'bg-gray-300/40'"
    >
      <span class="w-2 h-2 rounded-full" :class="active ? 'bg-green-500' : 'bg-gray-400'" />
    </span>

    <div class="flex-1 min-w-0">
      <h3 class="text-[15px] font-semibold text-black">{{ title }}</h3>
      <div v-if="subtitle" class="flex items-center gap-2 text-meta text-[13px] mt-1">
        <AppIcon :name="subtitleIcon" :size="14" />
        <!-- @click.stop keeps the subtitle link from triggering the card's own link. -->
        <RouterLink v-if="subtitleTo" :to="subtitleTo" class="truncate hover:underline hover:text-ink" @click.stop>{{ subtitle }}</RouterLink>
        <span v-else class="truncate">{{ subtitle }}</span>
      </div>
      <p v-if="description" class="text-[14px] text-muted line-clamp-2 mt-3">{{ description }}</p>
      <div v-if="version || beta" class="flex items-center gap-2 mt-3">
        <Pill v-if="version" :label="version" variant="version" />
        <Pill v-if="beta" label="BETA" variant="beta" />
      </div>
    </div>

    <div class="flex items-center gap-4 shrink-0 pt-1">
      <span v-if="updated" class="text-meta text-[13px] whitespace-nowrap">{{ updated }}</span>
      <AppIcon v-if="to" name="chevron-right" :size="18" class="text-label-inactive group-hover:text-meta" />
    </div>
  </component>
</template>
