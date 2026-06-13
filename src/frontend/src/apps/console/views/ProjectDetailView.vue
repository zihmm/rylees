<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useProjectsStore } from '../stores/projects.js';
import { getReleaseHistory } from '../../../shared/api.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TokenField from '../components/TokenField.vue';
import ReleaseNotesPanel from '../components/ReleaseNotesPanel.vue';

const route = useRoute();
const store = useProjectsStore();
const { customerId, id } = route.params;

const project = computed(() => store.currentProject);
const releaseNotes = ref([]);

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
