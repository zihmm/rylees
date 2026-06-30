<script setup>
import TimelineEntry from './TimelineEntry.vue';

defineProps({
  items: { type: Array, default: () => [] },
  translating: { type: Boolean, default: false },
  language: { type: String, default: 'de' },
});
</script>

<template>
  <div class="pb-12">
    <!-- The vertical timeline line is rendered by the parent slide so it can
         span past the pagination down to the card's bottom border. -->
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
