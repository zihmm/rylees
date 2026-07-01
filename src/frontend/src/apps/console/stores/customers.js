import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  getCustomers,
  getCustomer,
  createCustomer,
  updateCustomer,
  deleteCustomer,
} from '../../../shared/api.js';

export const useCustomersStore = defineStore('customers', () => {
  const customers = ref([]);
  const currentCustomer = ref(null);
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

  async function fetchCustomers(page = 1, perPage = 20) {
    const response = await getCustomers(page, perPage);
    customers.value = response.data.data;
    pagination.value = response.data.meta;
  }

  async function fetchCustomer(id) {
    const response = await getCustomer(id);
    currentCustomer.value = response.data;
    return response.data;
  }

  async function storeCustomer(payload) {
    return createCustomer(payload);
  }

  async function patchCustomer(id, payload) {
    const response = await updateCustomer(id, payload);
    currentCustomer.value = response.data;
    return response.data;
  }

  async function removeCustomer(id) {
    await deleteCustomer(id);
    if (currentCustomer.value?.id === id) currentCustomer.value = null;
  }

  return {
    customers,
    currentCustomer,
    pagination,
    fetchCustomers,
    fetchCustomer,
    storeCustomer,
    patchCustomer,
    removeCustomer,
  };
});
