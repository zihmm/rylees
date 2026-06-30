<script setup>
import { relative } from '../../../shared/date.js';
import MarkdownBody from '../../../shared/MarkdownBody.vue';
import Pill from './Pill.vue';
import StatusDot from './StatusDot.vue';

defineProps({
  items: { type: Array, default: () => [] }, // [{ id, version, body, publishedAt }]
});
</script>

<template>
  <section>
    <h2 class="text-[14px] font-semibold text-sidebar-heading mb-6">Release Notes</h2>

    <p v-if="!items.length" class="text-meta text-[13px]">No release notes yet.</p>

    <ul v-else class="space-y-6">
      <li v-for="note in items" :key="note.id" class="border-b border-field-border pb-6 last:border-0">
        <div class="flex gap-3">
          <StatusDot variant="green" class="mt-0.5" />
          <MarkdownBody :html="note.body" class="text-[14px] text-black line-clamp-3" />
        </div>
        <div class="flex items-center justify-between mt-2 pl-7">
          <span class="text-meta text-[13px]">{{ relative(note.publishedAt) }}</span>
          <Pill v-if="note.version" :label="'v' + note.version" variant="version" />
        </div>
      </li>
    </ul>
  </section>
</template>
