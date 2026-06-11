<script setup>
import { ref } from 'vue';
import MajorGroup from './MajorGroup.vue';

defineProps({
  groups: { type: Array, default: () => [] },
  language: { type: String, default: 'de' },
});

// Independent open state per major (closed by default).
const openMajors = ref(new Set());

function toggle(major) {
  const next = new Set(openMajors.value);
  if (next.has(major)) {
    next.delete(major);
  } else {
    next.add(major);
  }
  openMajors.value = next;
}
</script>

<template>
  <div>
    <MajorGroup
      v-for="(group, index) in groups"
      :key="group.major"
      :group="group"
      :index="index"
      :open="openMajors.has(group.major)"
      :language="language"
      @toggle="toggle(group.major)"
    />
  </div>
</template>
