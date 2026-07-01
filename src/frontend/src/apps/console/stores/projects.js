import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  getProjects,
  getProject,
  createProject,
  updateProject,
  deleteProject,
  getAllProjects,
} from '../../../shared/api.js';

export const useProjectsStore = defineStore('projects', () => {
  const projects = ref([]);
  const allProjects = ref([]); // global Projects overview
  const currentProject = ref(null);

  async function fetchProjects(customerId) {
    const response = await getProjects(customerId);
    projects.value = response.data.data;
  }

  async function fetchAllProjects() {
    const response = await getAllProjects();
    allProjects.value = response.data.data ?? [];
  }

  async function fetchProject(customerId, id) {
    const response = await getProject(customerId, id);
    currentProject.value = response.data;
    return response.data;
  }

  async function storeProject(customerId, payload) {
    return createProject(customerId, payload);
  }

  async function patchProject(customerId, id, payload) {
    const response = await updateProject(customerId, id, payload);
    currentProject.value = response.data;
    return response.data;
  }

  async function removeProject(customerId, id) {
    await deleteProject(customerId, id);
    currentProject.value = null;
  }

  return {
    projects,
    allProjects,
    currentProject,
    fetchProjects,
    fetchAllProjects,
    fetchProject,
    storeProject,
    patchProject,
    removeProject,
  };
});
