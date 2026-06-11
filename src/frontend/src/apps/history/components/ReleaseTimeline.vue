<script setup>
import TimelineEntry from './TimelineEntry.vue';

defineProps({
  items: { type: Array, default: () => [] },
  translating: { type: Boolean, default: false },
  language: { type: String, default: 'de' },
});
</script>

<template>
  <div class="relative">
    <!-- Continuous vertical timeline line -->
    <span
      class="absolute left-[10px] top-2 bottom-2 w-px bg-card-border"
      aria-hidden="true"
    ></span>

    <ol class="relative">
      <TimelineEntry
        v-for="(item, index) in items"
        :key="item.id"
        :item="item"
        :is-newest="index === 0"
        :translating="translating"
        :language="language"
      />
    </ol>
  </div>
</template>
