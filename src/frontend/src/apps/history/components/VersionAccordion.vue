<script setup>
import { ref, watch } from 'vue';
import MajorGroup from './MajorGroup.vue';

const props = defineProps({
  groups: { type: Array, default: () => [] },
  language: { type: String, default: 'de' },
});

// Independent open state per major. The first group starts open; the rest are
// closed and toggled by the user.
const openMajors = ref(new Set());
let autoOpened = false;
watch(
  () => props.groups,
  (groups) => {
    if (!autoOpened && groups && groups.length) {
      openMajors.value = new Set([groups[0].major]);
      autoOpened = true;
    }
  },
  { immediate: true }
);

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
  <div class="pb-12">
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
