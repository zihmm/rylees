<script setup>
import { ref, inject, onMounted } from 'vue';
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
const translating = ref(false);

// Flip face state: 'a' = timeline (Release Notes), 'b' = accordion (History).
const face = ref('a');

const { currentVersion, majorGroups } = useVersionGrouping(displayItems);

onMounted(async () => {
  try {
    const response = await getReleaseHistory(customerSlug, projectKey);
    const data = response.data;
    project.value = data.project;
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
});

// Language switching plumbing retained (no switcher UI per DESIGN-SPEC-RH §8 deviation 2).
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

function flipToHistory() {
  face.value = 'b';
}

function flipToNotes() {
  face.value = 'a';
}

// Exposed for tests (drives language switching without a UI switcher).
defineExpose({ switchLanguage, face, activeLanguage });
</script>

<template>
  <HistoryCard :project-name="project?.name || ''">
    <!-- State label per face -->
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

    <!-- Header button per face -->
    <template #button>
      <button
        v-if="face === 'a'"
        type="button"
        class="flex items-center justify-center w-10 h-[37px] rounded-card bg-[rgba(217,217,217,0.34)] text-title"
        aria-label="Show release history"
        @click="flipToHistory"
      >
        <AppIcon name="clock" :size="20" />
      </button>
      <button
        v-else
        type="button"
        class="flex items-center justify-center w-10 h-[37px] rounded-card bg-[rgba(217,217,217,0.34)] text-title"
        aria-label="Back to release notes"
        @click="flipToNotes"
      >
        <AppIcon name="arrow-left" :size="20" />
      </button>
    </template>

    <!-- Flip container: both faces present, preserve-3d -->
    <div class="flip-scene">
      <div class="flip-card" :class="{ 'is-flipped': face === 'b' }">
        <div class="flip-face flip-face--front">
          <ReleaseTimeline
            :items="displayItems"
            :translating="translating"
            :language="activeLanguage"
          />
        </div>
        <div class="flip-face flip-face--back">
          <VersionAccordion :groups="majorGroups" :language="activeLanguage" />
        </div>
      </div>
    </div>
  </HistoryCard>
</template>

<style scoped>
.flip-scene {
  perspective: 1600px;
}

.flip-card {
  position: relative;
  transform-style: preserve-3d;
  transition: transform 0.6s ease;
}

.flip-card.is-flipped {
  transform: rotateY(180deg);
}

.flip-face {
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
}

.flip-face--back {
  position: absolute;
  inset: 0;
  transform: rotateY(180deg);
}
</style>
