<script setup>
import { ref, computed, inject, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import { getReleaseHistory, translateReleaseHistory } from '../../../shared/api.js';
import { useVersionGrouping } from '../composables/useVersionGrouping.js';
import AppIcon from '../../../shared/icons/AppIcon.vue';
import HistoryCard from '../components/HistoryCard.vue';
import ReleaseTimeline from '../components/ReleaseTimeline.vue';
import VersionAccordion from '../components/VersionAccordion.vue';

const router = useRouter();
const customerSlug = inject('customerSlug');
const projectKey = inject('projectKey');

// --- Reactive state (per SPEC §10) ---
const project = ref(null);
const originalItems = ref([]); // original DE items from API, never overwritten
const displayItems = ref([]); // items currently shown (may be translated)
const activeLanguage = ref('de');
// Locale used to render the relative/absolute date labels — driven by the
// project's configured language (project settings), independent of the
// body-translation toggle above.
const projectLanguage = ref('en');
const translating = ref(false);

// View state: 'a' = timeline (Release Notes), 'b' = accordion (History).
// The two views sit side by side in a slider that slides horizontally between
// them, with the container height animated to the active view's height.
const face = ref('a');

const { currentVersion, majorGroups } = useVersionGrouping(displayItems);

// --- Release notes pagination (4 per page) ---
const PAGE_SIZE = 4;
const page = ref(0);
const pageCount = computed(() => Math.ceil(displayItems.value.length / PAGE_SIZE));
const pagedItems = computed(() =>
  displayItems.value.slice(page.value * PAGE_SIZE, page.value * PAGE_SIZE + PAGE_SIZE)
);

function prevPage() {
  if (page.value > 0) {
    animateHeight.value = true;
    page.value -= 1;
  }
}

function nextPage() {
  if (page.value < pageCount.value - 1) {
    animateHeight.value = true;
    page.value += 1;
  }
}

// Keep the page in range if the item count shrinks.
watch(pageCount, (count) => {
  if (page.value > count - 1) page.value = Math.max(0, count - 1);
});

// --- Slide + height animation ---
const timelineEl = ref(null);
const historyEl = ref(null);
const contentHeight = ref(null); // px; null until first measure
// Height transition stays off until the first user interaction (paging or
// switching view), so the container never animates on load/refresh — including
// late height settles from data load, web-font reflow, or ResizeObserver.
const animateHeight = ref(false);

function measureHeight() {
  const el = face.value === 'b' ? historyEl.value : timelineEl.value;
  if (el) contentHeight.value = el.offsetHeight;
}

let resizeObserver = null;

onMounted(async () => {
  // Keep the container height in sync as either view's content changes
  // (data load, body translation, accordion expand/collapse).
  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => measureHeight());
    if (timelineEl.value) resizeObserver.observe(timelineEl.value);
    if (historyEl.value) resizeObserver.observe(historyEl.value);
  }

  try {
    const response = await getReleaseHistory(customerSlug, projectKey);
    const data = response.data;
    project.value = data.project;
    projectLanguage.value = data.project.language || 'en';
    originalItems.value = data.items.map((item) => ({ ...item }));
    displayItems.value = originalItems.value.map((item) => ({ ...item }));
    document.title = `${project.value.name} — Release History`;
  } catch (error) {
    if (error.response?.status === 404) {
      router.replace({ name: 'not-found' });
    } else {
      router.replace({ name: 'not-found' });
    }
  }

  await nextTick();
  measureHeight();
});

onBeforeUnmount(() => {
  if (resizeObserver) resizeObserver.disconnect();
});

// Re-measure when the active view changes so the height animates to it.
watch(face, async () => {
  await nextTick();
  measureHeight();
});

async function switchLanguage(lang) {
  if (lang === activeLanguage.value) return;
  if (lang === 'de') {
    // Restore original bodies — no API call.
    displayItems.value = originalItems.value.map((item) => ({ ...item }));
    activeLanguage.value = 'de';
    return;
  }
  translating.value = true;
  try {
    const response = await translateReleaseHistory(customerSlug, projectKey, lang);
    const translatedMap = Object.fromEntries(
      response.data.items.map((i) => [i.id, i.body])
    );
    displayItems.value = originalItems.value.map((item) => ({
      ...item,
      body: translatedMap[item.id] ?? item.body,
    }));
    activeLanguage.value = lang;
  } finally {
    translating.value = false;
  }
}

function showHistory() {
  animateHeight.value = true;
  face.value = 'b';
}

function showNotes() {
  animateHeight.value = true;
  face.value = 'a';
}

// Exposed for tests (drives language switching without a UI switcher).
defineExpose({ switchLanguage, face, activeLanguage });
</script>

<template>
  <HistoryCard :project-name="project?.name || ''">
    <!-- State label per view -->
    <template #label>
      <span
        v-if="face === 'a'"
        class="text-[15px] font-semibold text-accent"
      >v{{ currentVersion }}</span>
      <span
        v-else
        class="text-[15px] font-semibold text-label-inactive"
      >History</span>
    </template>

    <!-- Header button per view. DE/EN/FR language switcher is deferred to a
         later feature (DESIGN-SPEC-RH §8); switchLanguage stays wired for it. -->
    <template #button>
      <button
        v-if="face === 'a'"
        type="button"
        class="flex items-center justify-center w-10 h-[37px] rounded-card bg-[rgba(217,217,217,0.34)] text-title"
        aria-label="Show release history"
        @click="showHistory"
      >
        <AppIcon name="clock" :size="20" />
      </button>
      <button
        v-else
        type="button"
        class="flex items-center justify-center w-10 h-[37px] rounded-card bg-[rgba(217,217,217,0.34)] text-title"
        aria-label="Back to release notes"
        @click="showNotes"
      >
        <AppIcon name="arrow-left" :size="20" />
      </button>
    </template>

    <!-- Slider: both views sit side by side; the track slides horizontally and
         the viewport height animates to the active view. -->
    <div
      class="slider-viewport"
      :class="{ 'animate-height': animateHeight }"
      :style="{ height: contentHeight !== null ? contentHeight + 'px' : undefined }"
    >
      <div class="slider-track" :class="{ 'show-history': face === 'b' }">
        <div ref="historyEl" class="slide">
          <VersionAccordion :groups="majorGroups" :language="projectLanguage" />
        </div>
        <div ref="timelineEl" class="slide relative">
          <!-- Timeline rail: spans the whole slide so it reaches the card's
               bottom border on every pagination page (past the pagination). -->
          <span
            class="absolute left-[10px] top-2 bottom-0 w-px bg-card-border"
            aria-hidden="true"
          ></span>
          <ReleaseTimeline
            :items="pagedItems"
            :translating="translating"
            :language="projectLanguage"
          />
          <!-- Pagination: centered prev/next arrows; each disabled at its edge. -->
          <div v-if="displayItems.length" class="flex items-center justify-center gap-4 pb-12">
            <button
              type="button"
              class="flex items-center justify-center w-9 h-9 rounded-full border border-card-border text-title transition-colors hover:border-accent disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:border-card-border"
              :disabled="page === 0"
              aria-label="Newer releases"
              @click="prevPage"
            >
              <AppIcon name="chevron-left" :size="18" />
            </button>
            <button
              type="button"
              class="flex items-center justify-center w-9 h-9 rounded-full border border-card-border text-title transition-colors hover:border-accent disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:border-card-border"
              :disabled="page >= pageCount - 1"
              aria-label="Older releases"
              @click="nextPage"
            >
              <AppIcon name="chevron-right" :size="18" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </HistoryCard>
</template>

<style scoped>
/* Easing: easeOutQuint — a smooth, decelerating glide. */
.slider-viewport {
  overflow: hidden;
}

/* Height only animates after the initial visit has settled. */
.slider-viewport.animate-height {
  transition: height 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}

.slider-track {
  display: flex;
  /* Each view keeps its own height (no stretch) so the viewport can animate
     between them. */
  align-items: flex-start;
  width: 200%;
  /* Default shows the timeline (the right slide). */
  transform: translateX(-50%);
  transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}

/* Slide to the right to reveal the versions (the left slide). */
.slider-track.show-history {
  transform: translateX(0);
}

.slide {
  width: 50%;
  flex: 0 0 50%;
}
</style>
