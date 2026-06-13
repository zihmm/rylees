<script setup>
import { onMounted, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useProjectsStore } from '../stores/projects.js';
import AppLogo from './AppLogo.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';
import UserMenu from './UserMenu.vue';
import StatusDot from './StatusDot.vue';

const route = useRoute();
const projectsStore = useProjectsStore();

const nav = [
  { name: 'dashboard', to: { name: 'dashboard' }, label: 'Dashboard', icon: 'dashboard', match: '/dashboard' },
  { name: 'customers', to: { name: 'customers' }, label: 'Customers', icon: 'briefcase', match: '/customers' },
  { name: 'projects', to: { name: 'projects' }, label: 'Projects', icon: 'folder', match: '/projects' },
];

function isActive(match) {
  return route.path === match || route.path.startsWith(match + '/');
}

// Show the most recently updated projects (the endpoint returns all, newest first).
const currentProjects = computed(() => (projectsStore.allProjects || []).slice(0, 8));
const dotVariants = ['green', 'rose', 'gray'];

onMounted(async () => {
  // Best-effort: the global /projects endpoint may not exist yet.
  try {
    await projectsStore.fetchAllProjects();
  } catch {
    /* ignore — Current Projects list stays empty */
  }
});
</script>

<template>
  <nav class="w-[274px] shrink-0 bg-panel h-screen flex flex-col">
    <div class="px-6 py-10">
      <AppLogo :size="50" />
    </div>

    <!-- Scrolls internally so the UserMenu stays pinned to the bottom of the viewport. -->
    <div class="flex-1 min-h-0 overflow-y-auto">
      <div class="px-4">
        <p class="px-2 text-[14px] font-medium text-muted mb-2">Navigation</p>
        <ul>
          <li v-for="item in nav" :key="item.name">
            <RouterLink
              :to="item.to"
              class="flex items-center gap-3 px-2 py-2.5 text-[14px] font-medium rounded"
              :class="isActive(item.match) ? 'text-accent' : 'text-ink hover:text-black'"
            >
              <AppIcon :name="item.icon" :size="19" />
              {{ item.label }}
            </RouterLink>
          </li>
        </ul>
      </div>

      <div v-if="currentProjects.length" class="px-4 mt-8">
        <p class="px-2 text-[14px] font-medium text-[#777] mb-2">Current Projects</p>
        <ul>
          <li v-for="(p, i) in currentProjects" :key="p.id || i" class="flex items-center gap-3 px-2 py-2 text-[14px] text-ink">
            <StatusDot :variant="dotVariants[i % dotVariants.length]" />
            <span class="truncate">{{ p.name }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="px-4 pb-4 pt-4 border-t border-field-border">
      <UserMenu />
    </div>
  </nav>
</template>
