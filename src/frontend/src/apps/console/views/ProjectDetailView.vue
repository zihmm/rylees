<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProjectsStore } from '../stores/projects.js';
import { getReleaseHistory } from '../../../shared/api.js';
import { absolute } from '../../../shared/date.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TokenField from '../components/TokenField.vue';
import AppButton from '../components/AppButton.vue';

const route = useRoute();
const router = useRouter();
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

function summary(body = '') {
  return body.length > 100 ? `${body.slice(0, 100)}...` : body;
}
</script>

<template>
  <ConsoleLayout
    v-if="project"
    :parent="{ label: 'Projects', to: '/projects' }"
    :current="project.name"
  >
    <div class="flex justify-end mb-2">
      <AppButton variant="secondary" @click="router.push(`/customers/${customerId}/projects/${id}/edit`)">Edit project</AppButton>
    </div>

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

    <section class="mt-8">
      <h2 class="text-[15px] font-semibold mb-3">Release history</h2>
      <p v-if="!releaseNotes.length" class="text-meta text-[14px]">No release notes yet.</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-[14px]">
          <thead class="text-[12px] uppercase text-meta border-b border-field-border">
            <tr>
              <th class="py-2 pr-4 font-medium">Version</th>
              <th class="py-2 pr-4 font-medium">Published</th>
              <th class="py-2 font-medium">Summary</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-field-border">
            <tr v-for="note in releaseNotes" :key="note.id">
              <td class="py-3 pr-4 whitespace-nowrap">v{{ note.version }}</td>
              <td class="py-3 pr-4 whitespace-nowrap">{{ absolute(note.publishedAt) }}</td>
              <td class="py-3">{{ summary(note.body || '') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </ConsoleLayout>
</template>
