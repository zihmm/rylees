<script setup>
import { onMounted, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useProjectsStore } from '../stores/projects.js';
import AppLogo from './AppLogo.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';
import UserMenu from './UserMenu.vue';

const route = useRoute();
const projectsStore = useProjectsStore();

const nav = [
  { name: 'dashboard', to: { name: 'dashboard' }, label: 'Dashboard', icon: 'dashboard', match: '/dashboard' },
  { name: 'customers', to: { name: 'customers' }, label: 'Organisations', icon: 'briefcase', match: '/customers' },
  { name: 'projects', to: { name: 'projects' }, label: 'Projects', icon: 'folder', match: '/projects' },
];

function isActive(match) {
  return route.path === match || route.path.startsWith(match + '/');
}

const currentProjects = computed(() => projectsStore.allProjects || []);
const dotColors = ['bg-green-500', 'bg-rose-500', 'bg-gray-300'];

onMounted(async () => {
  // Best-effort: the global /projects endpoint may not exist yet.
  try {
    await projectsStore.fetchAllProjects(1, 8);
  } catch {
    /* ignore — Current Projects list stays empty */
  }
});
</script>

<template>
  <nav class="w-[274px] shrink-0 bg-panel min-h-screen flex flex-col">
    <div class="px-6 pt-8 pb-6">
      <AppLogo :size="34" />
    </div>

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
          <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="dotColors[i % dotColors.length]" />
          <span class="truncate">{{ p.name }}</span>
        </li>
      </ul>
    </div>

    <div class="mt-auto px-4 pb-4 pt-4 border-t border-field-border">
      <UserMenu />
    </div>
  </nav>
</template>
