<script setup>
import { relative } from '../../../shared/date.js';
import Pill from './Pill.vue';

defineProps({
  items: { type: Array, default: () => [] }, // [{ id, version, body, publishedAt }]
});
const dotColors = ['bg-green-500', 'bg-gray-300', 'bg-accent'];
</script>

<template>
  <section>
    <h2 class="text-[14px] font-semibold text-sidebar-heading mb-6">Release Notes</h2>

    <p v-if="!items.length" class="text-meta text-[13px]">No release notes yet.</p>

    <ul v-else class="space-y-6">
      <li v-for="(note, i) in items" :key="note.id" class="border-b border-field-border pb-6 last:border-0">
        <div class="flex gap-3">
          <span class="w-2.5 h-2.5 rounded-full shrink-0 mt-1.5" :class="dotColors[i % dotColors.length]" />
          <p class="text-[14px] text-black line-clamp-3">{{ note.body }}</p>
        </div>
        <div class="flex items-center justify-between mt-2 pl-5">
          <span class="text-meta text-[13px]">{{ relative(note.publishedAt) }}</span>
          <Pill v-if="note.version" :label="'v' + note.version" variant="version" />
        </div>
      </li>
    </ul>
  </section>
</template>
