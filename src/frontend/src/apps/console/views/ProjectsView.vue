<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useProjectsStore } from '../stores/projects.js';
import { relative } from '../../../shared/date.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import OverviewCard from '../components/OverviewCard.vue';
import EmptyState from '../components/EmptyState.vue';
import AppButton from '../components/AppButton.vue';
import emptyArt from '../../../assets/illustrations/empty-projects.svg';

const router = useRouter();
const store = useProjectsStore();
const failed = ref(false);

onMounted(async () => {
  try {
    await store.fetchAllProjects(1, 100);
  } catch {
    failed.value = true;
  }
});

const projects = computed(() => store.allProjects || []);

// The aggregate item may carry a nested customer for the detail link.
function projectTo(p) {
  const cid = p.customer?.id || p.customer_id;
  return cid ? `/customers/${cid}/projects/${p.id}` : '/customers';
}
</script>

<template>
  <ConsoleLayout current="Projects">
    <div v-if="projects.length">
      <OverviewCard
        v-for="p in projects"
        :key="p.id"
        :to="projectTo(p)"
        :title="p.name"
        :subtitle="p.customer?.name || ''"
        :description="p.description || ''"
        :version="p.version ? 'v' + p.version : ''"
        :updated="p.updated_at ? 'Updated ' + relative(p.updated_at) : ''"
      />
    </div>
    <p v-else-if="failed" class="text-meta">Projects overview is not available yet.</p>
    <p v-else class="text-meta">No projects yet.</p>

    <template #sidebar>
      <EmptyState :image="emptyArt" caption="Create a project from one of your organisations">
        <AppButton icon="plus" @click="router.push('/customers')">New Project</AppButton>
      </EmptyState>
    </template>
  </ConsoleLayout>
</template>
