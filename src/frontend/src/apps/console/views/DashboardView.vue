<script setup>
import { computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { useCustomersStore } from '../stores/customers.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';

const auth = useAuthStore();
const customersStore = useCustomersStore();

onMounted(() => customersStore.fetchCustomers(1, 100));

const pagination = computed(() => customersStore.pagination);
const totalProjects = computed(() =>
  customersStore.customers.reduce((sum, c) => sum + (c.projects_count || 0), 0)
);
</script>

<template>
  <ConsoleLayout current="Dashboard">
    <h1 class="text-2xl font-semibold text-black mb-8">
      Welcome back, {{ auth.user?.profile?.firstname }}!
    </h1>
    <div class="grid grid-cols-2 gap-6 max-w-xl">
      <div class="rounded-card border border-field-border p-6">
        <p class="text-meta text-[13px] mb-1">Customers</p>
        <p class="text-3xl font-semibold text-black">{{ pagination.total }}</p>
      </div>
      <div class="rounded-card border border-field-border p-6">
        <p class="text-meta text-[13px] mb-1">Projects</p>
        <p class="text-3xl font-semibold text-black">{{ totalProjects }}</p>
      </div>
    </div>
  </ConsoleLayout>
</template>
