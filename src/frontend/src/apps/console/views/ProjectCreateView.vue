<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getLlmTonalities, getLlmTemperatures, getCustomers } from '../../../shared/api.js';
import { useProjectsStore } from '../stores/projects.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TextField from '../components/TextField.vue';
import SelectField from '../components/SelectField.vue';
import TextArea from '../components/TextArea.vue';
import AppButton from '../components/AppButton.vue';

const route = useRoute();
const router = useRouter();
const store = useProjectsStore();
// Reached either from a customer (customerId in the path) or globally from
// the Projects overview, where the customer is picked via the dropdown below.
const routeCustomerId = route.params.customerId || '';
const isGlobal = !routeCustomerId;

const LANGUAGES = [
  { value: 'en', label: 'English' },
  { value: 'de', label: 'German' },
  { value: 'fr', label: 'French' },
];

const customers = ref([]);
const tonalities = ref([]);
const temperatures = ref([]);
const errors = ref({});
const saving = ref(false);
const form = reactive({ customer_id: routeCustomerId, name: '', description: '', language: 'en', llm_tonality_id: '', llm_temperature_id: '' });

onMounted(async () => {
  const requests = [getLlmTonalities(), getLlmTemperatures()];
  if (isGlobal) requests.push(getCustomers(1, 100));
  const [ton, temp, custs] = await Promise.all(requests);
  tonalities.value = ton.data.items.map((i) => ({ value: i.id, label: i.name }));
  temperatures.value = temp.data.items.map((i) => ({ value: i.id, label: `${i.name} (${i.value})` }));
  if (custs) customers.value = custs.data.data.map((c) => ({ value: c.id, label: c.organisation?.name || '(unnamed)' }));
});

function err(key) {
  const v = errors.value[key];
  return Array.isArray(v) ? v[0] : v || '';
}

async function save() {
  errors.value = {};
  if (isGlobal && !form.customer_id) {
    errors.value = { customer_id: 'Please select a customer.' };
    return;
  }
  const customerId = form.customer_id;
  const payload = {
    name: form.name,
    description: form.description,
    language: form.language, // new project field (backend-dependent, DESIGN-SPEC-DL §11.4)
    llm_tonality_id: form.llm_tonality_id,
    llm_temperature_id: form.llm_temperature_id,
  };
  saving.value = true;
  try {
    const res = await store.storeProject(customerId, payload);
    // From the global Projects screen, return to the overview; from a customer
    // context, drop into the freshly created project.
    router.push(isGlobal ? '/projects' : `/customers/${customerId}/projects/${res.data.id}`);
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {};
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <ConsoleLayout :parent="isGlobal ? { label: 'Projects', to: '/projects' } : { label: 'Customers', to: '/customers' }" current="New Project">
    <form @submit.prevent="save">
      <SelectField v-if="isGlobal" v-model="form.customer_id" label="Customer" :options="customers" placeholder="—" required :error="err('customer_id')" />
      <TextField v-model="form.name" label="Name" required :error="err('name')" />
      <SelectField v-model="form.language" label="Language" :options="LANGUAGES" :error="err('language')" />
      <SelectField v-model="form.llm_tonality_id" label="LLM tonality" :options="tonalities" placeholder="—" required :error="err('llm_tonality_id')" />
      <SelectField v-model="form.llm_temperature_id" label="LLM temperature" :options="temperatures" placeholder="—" required :error="err('llm_temperature_id')" />
      <TextArea v-model="form.description" label="Description" :error="err('description')" />
    </form>

    <template #footer-actions>
      <AppButton variant="secondary" @click="router.push(isGlobal ? '/projects' : `/customers/${routeCustomerId}`)">Cancel</AppButton>
      <AppButton icon="check" :loading="saving" @click="save">Save</AppButton>
    </template>
  </ConsoleLayout>
</template>
