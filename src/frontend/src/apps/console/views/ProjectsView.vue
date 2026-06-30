<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useProjectsStore } from '../stores/projects.js';
import { relative } from '../../../shared/date.js';
import { releaseHistoryDomain, releaseHistoryUrl } from '../../../shared/releaseHistory.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import OverviewCard from '../components/OverviewCard.vue';
import EmptyState from '../components/EmptyState.vue';
import AppButton from '../components/AppButton.vue';
import emptyArt from '../../../assets/illustrations/projects-overview.svg';

const router = useRouter();
const store = useProjectsStore();
const failed = ref(false);

onMounted(async () => {
  try {
    await store.fetchAllProjects();
  } catch {
    failed.value = true;
  }
});

const projects = computed(() => store.allProjects || []);
</script>

<template>
  <ConsoleLayout current="Projects">
    <template #header-actions>
      <AppButton icon="plus" @click="router.push('/projects/new')">New Project</AppButton>
    </template>

    <div v-if="projects.length">
      <OverviewCard
        v-for="p in projects"
        :key="p.id"
        :to="`/customers/${p.customer_id}/projects/${p.id}/edit`"
        :title="p.name"
        :subtitle="p.customer_name || ''"
        :subtitle-to="`/customers/${p.customer_id}/edit`"
        :link-href="releaseHistoryUrl(p.organisation_slug, p.key)"
        :link-label="releaseHistoryDomain(p.organisation_slug, p.key)"
        :version="p.version ? 'v' + p.version : ''"
        :updated="p.updated_at ? 'Updated ' + relative(p.updated_at) : ''"
        active
      />
    </div>
    <p v-else-if="failed" class="text-meta">Projects overview is not available yet.</p>
    <p v-else class="text-meta">No projects yet.</p>

    <template #sidebar>
      <EmptyState :image="emptyArt" caption="Dive into your projects — the water's fine down here." />
    </template>
  </ConsoleLayout>
</template>
