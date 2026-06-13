<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useCustomersStore } from '../stores/customers.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import OverviewCard from '../components/OverviewCard.vue';
import EmptyState from '../components/EmptyState.vue';
import AppButton from '../components/AppButton.vue';
import emptyArt from '../../../assets/illustrations/customers-overview.svg';

const router = useRouter();
const store = useCustomersStore();
const perPage = 20;

onMounted(() => store.fetchCustomers(1, perPage));

const customers = computed(() => store.customers);
const pagination = computed(() => store.pagination);

function changePage(delta) {
  const next = pagination.value.current_page + delta;
  if (next >= 1 && next <= pagination.value.last_page) store.fetchCustomers(next, perPage);
}
</script>

<template>
  <ConsoleLayout current="Customers">
    <template #header-actions>
      <AppButton icon="plus" @click="router.push('/customers/new')">New Customer</AppButton>
    </template>

    <div v-if="customers.length">
      <OverviewCard
        v-for="c in customers"
        :key="c.id"
        :to="`/customers/${c.id}`"
        :title="c.organisation?.name"
        :subtitle="c.main_contact ? `${c.main_contact.firstname} ${c.main_contact.lastname}` : ''"
        subtitle-icon="user"
        active
      />
      <div v-if="pagination.last_page > 1" class="flex items-center gap-4 pt-6 text-[13px] text-meta">
        <button class="underline disabled:opacity-40" :disabled="pagination.current_page <= 1" @click="changePage(-1)">Previous</button>
        <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="underline disabled:opacity-40" :disabled="pagination.current_page >= pagination.last_page" @click="changePage(1)">Next</button>
      </div>
    </div>
    <p v-else class="text-meta">No organisations yet.</p>

    <template #sidebar>
      <EmptyState :image="emptyArt" caption="Customers are like plants — add one and remember to water it now and then." />
    </template>
  </ConsoleLayout>
</template>
