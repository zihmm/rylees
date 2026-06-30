<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useProjectsStore } from '../stores/projects.js';
import { getReleaseHistory } from '../../../shared/api.js';
import { releaseHistoryDomain, releaseHistoryUrl } from '../../../shared/releaseHistory.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';
import TokenField from '../components/TokenField.vue';
import ReleaseNotesPanel from '../components/ReleaseNotesPanel.vue';

const route = useRoute();
const store = useProjectsStore();
const { customerId, id } = route.params;

const project = computed(() => store.currentProject);
const releaseNotes = ref([]);

const historyDomain = computed(() =>
  releaseHistoryDomain(project.value?.customer?.organisation_slug, project.value?.key)
);
const historyUrl = computed(() =>
  releaseHistoryUrl(project.value?.customer?.organisation_slug, project.value?.key)
);

onMounted(async () => {
  const p = await store.fetchProject(customerId, id);
  try {
    const slug = p.customer?.organisation_slug;
    if (slug && p.key) {
      const res = await getReleaseHistory(slug, p.key);
      releaseNotes.value = res.data.items || [];
    }
  } catch {
    /* public history may be unavailable */
  }
});
</script>

<template>
  <ConsoleLayout
    v-if="project"
    :parent="{ label: 'Projects', to: '/projects' }"
    :current="project.name"
  >
    <dl>
      <div class="flex flex-col sm:flex-row gap-2 sm:gap-8 py-6 border-b border-field-border">
        <dt class="sm:w-48 shrink-0 text-[13px] text-field-label">Name</dt>
        <dd class="flex-1 text-[14px] text-black">{{ project.name }}</dd>
      </div>

      <div v-if="historyDomain" class="flex flex-col sm:flex-row gap-2 sm:gap-8 py-6 border-b border-field-border">
        <dt class="sm:w-48 shrink-0 text-[13px] text-field-label sm:pt-3">Release history</dt>
        <dd class="flex-1 max-w-md">
          <div class="flex items-center gap-2 h-11 rounded-field border border-field-border bg-[rgba(233,233,234,0.42)] px-4">
            <input
              :value="historyDomain"
              disabled
              class="flex-1 min-w-0 bg-transparent text-[14px] text-black truncate outline-none disabled:opacity-100"
            />
            <a
              :href="historyUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="text-helper hover:text-black shrink-0"
              aria-label="Open release history in a new tab"
            >
              <AppIcon name="external-link" :size="16" />
            </a>
          </div>
        </dd>
      </div>

      <TokenField :token="project.token || ''" />

      <div class="flex flex-col sm:flex-row gap-2 sm:gap-8 py-6 border-b border-field-border">
        <dt class="sm:w-48 shrink-0 text-[13px] text-field-label">LLM tonality</dt>
        <dd class="flex-1 text-[14px] text-black">{{ project.llm?.tonality || '—' }}</dd>
      </div>
      <div class="flex flex-col sm:flex-row gap-2 sm:gap-8 py-6 border-b border-field-border">
        <dt class="sm:w-48 shrink-0 text-[13px] text-field-label">LLM temperature</dt>
        <dd class="flex-1 text-[14px] text-black">{{ project.llm?.temperature ?? '—' }}</dd>
      </div>
      <div class="flex flex-col sm:flex-row gap-2 sm:gap-8 py-6 border-b border-field-border">
        <dt class="sm:w-48 shrink-0 text-[13px] text-field-label">Description</dt>
        <dd class="flex-1 text-[14px] text-black whitespace-pre-line">{{ project.description || '—' }}</dd>
      </div>
    </dl>

    <template #sidebar>
      <ReleaseNotesPanel :items="releaseNotes" />
    </template>
  </ConsoleLayout>
</template>
