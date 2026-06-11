<script setup>
import { computed } from 'vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';
import SubVersionRow from './SubVersionRow.vue';
import { relative } from '../../../shared/date.js';

const props = defineProps({
  group: { type: Object, required: true },
  index: { type: Number, default: 0 },
  open: { type: Boolean, default: false },
  language: { type: String, default: 'de' },
});

defineEmits(['toggle']);

// Cycle a soft bookmark tint per major group.
const tints = ['text-[#8b5cf6]', 'text-[#ffc00e]', 'text-[#ec4899]'];
const tintClass = computed(() => tints[props.index % tints.length]);

// "Last update" derived from the newest sub-version's publishedAt.
const lastUpdate = computed(() => {
  const newest = props.group.items[0];
  return newest ? relative(newest.publishedAt, props.language) : '';
});
</script>

<template>
  <div class="relative border-b border-card-border last:border-b-0">
    <button
      type="button"
      class="w-full flex items-center gap-3 py-4 text-left"
      :aria-expanded="open"
      @click="$emit('toggle')"
    >
      <span class="shrink-0 rounded-full bg-pill-bg p-2 flex items-center justify-center" :class="tintClass">
        <AppIcon name="bookmark" :size="20" />
      </span>

      <span class="flex-1 min-w-0">
        <span class="block text-[15px] leading-[22px] font-bold text-black">
          Version {{ group.major }}.x
        </span>
        <span class="block text-[13px] leading-[22px] text-muted">
          {{ lastUpdate }}
        </span>
      </span>

      <span class="shrink-0 text-muted" aria-hidden="true">
        <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" :size="20" />
      </span>
    </button>

    <div v-if="open" class="relative pl-5 pb-4">
      <!-- Group vertical line for the connector stubs -->
      <span class="absolute left-5 top-0 bottom-4 w-px bg-card-border" aria-hidden="true"></span>
      <ol>
        <SubVersionRow
          v-for="item in group.items"
          :key="item.id"
          :item="item"
          :language="language"
        />
      </ol>
    </div>
  </div>
</template>
