<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import TimelineEntry from './TimelineEntry.vue';

const props = defineProps({
  items: { type: Array, default: () => [] },
  translating: { type: Boolean, default: false },
  language: { type: String, default: 'de' },
});

// As the reader scrolls down, a release note becomes the active (outlined)
// bubble once its top reaches this distance from the top of the viewport.
const ACTIVE_OFFSET = 200;

const listEl = ref(null);
const activeIndex = ref(0);
let frame = null;

function updateActive() {
  frame = null;
  const entries = listEl.value?.children;
  if (!entries || !entries.length) return;
  // The final notes can sit within the bottom ACTIVE_OFFSET of the page, so
  // their tops never reach the offset line however far you scroll. Once the
  // page is scrolled to its bottom, activate the last note regardless.
  const doc = document.documentElement;
  const scrollable = doc.scrollHeight > window.innerHeight;
  if (scrollable && window.innerHeight + window.scrollY >= doc.scrollHeight - 2) {
    activeIndex.value = entries.length - 1;
    return;
  }
  // The active entry is the lowest one whose top has crossed the offset line;
  // before any have, the newest (first) entry stays active.
  const tops = Array.from(entries, (el) => el.getBoundingClientRect().top);
  let active = 0;
  for (let i = 0; i < tops.length; i += 1) {
    if (tops[i] <= ACTIVE_OFFSET) active = i;
  }
  // Once the active marker scrolls above the viewport top its note has been
  // read, so hand the active ring to the next still-visible note. This keeps
  // the ring on screen and lets each bubble fall back to a dot as it passes,
  // reversing cleanly when the reader scrolls back up.
  while (active < tops.length - 1 && tops[active] < 0) {
    active += 1;
  }
  activeIndex.value = active;
}

function onScroll() {
  if (frame === null) frame = requestAnimationFrame(updateActive);
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  updateActive();
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('resize', onScroll);
  if (frame !== null) cancelAnimationFrame(frame);
});

// A page change or data swap resets to the newest entry, then re-measures.
watch(
  () => props.items,
  async () => {
    activeIndex.value = 0;
    await nextTick();
    updateActive();
  }
);
</script>

<template>
  <div class="pb-12">
    <!-- The vertical timeline line is rendered by the parent slide so it can
         span past the pagination down to the card's bottom border. -->
    <ol ref="listEl" class="relative">
      <TimelineEntry
        v-for="(item, index) in items"
        :key="item.id"
        :item="item"
        :is-active="index === activeIndex"
        :translating="translating"
        :language="language"
      />
    </ol>
  </div>
</template>
