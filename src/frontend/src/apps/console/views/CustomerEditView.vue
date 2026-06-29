<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getIndustries } from '../../../shared/api.js';
import { useCustomersStore } from '../stores/customers.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TextField from '../components/TextField.vue';
import SelectField from '../components/SelectField.vue';
import TextArea from '../components/TextArea.vue';
import AppButton from '../components/AppButton.vue';

const route = useRoute();
const router = useRouter();
const store = useCustomersStore();
const customerId = route.params.id;

const industries = ref([]);
const errors = ref({});
const saving = ref(false);
const form = reactive({
  name: '', street: '', postcode: '', city: '', website: '', email: '',
  industry_id: '', description: '',
});

onMounted(async () => {
  const [, c] = await Promise.all([
    getIndustries().then((r) => (industries.value = r.data.items.map((i) => ({ value: i.id, label: i.name })))),
    store.fetchCustomer(customerId),
  ]);
  const org = c.organisation || {};
  Object.assign(form, {
    name: org.name || '', street: org.street || '', postcode: org.postcode || '',
    city: org.city || '', website: org.website || '', email: org.email || '',
    industry_id: c.industry?.id || '', description: c.description || '',
  });
});

function err(key) {
  const v = errors.value[key];
  return Array.isArray(v) ? v[0] : v || '';
}

async function save() {
  errors.value = {};
  const payload = {
    organisation: {
      name: form.name, street: form.street, postcode: form.postcode,
      city: form.city, website: form.website, email: form.email,
    },
    industry_id: form.industry_id || null,
    description: form.description,
  };
  saving.value = true;
  try {
    await store.patchCustomer(customerId, payload);
    router.push(`/customers/${customerId}`);
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {};
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <ConsoleLayout :parent="{ label: 'Customers', to: '/customers' }" current="Edit Customer">
    <form @submit.prevent="save">
      <TextField v-model="form.name" label="Name" required :error="err('organisation.name')" />
      <TextField v-model="form.street" label="Street" :error="err('organisation.street')" />
      <TextField v-model="form.city" label="City" :error="err('organisation.city')" />
      <TextField v-model="form.postcode" label="Postcode" :error="err('organisation.postcode')" />
      <TextField v-model="form.website" label="Website" :error="err('organisation.website')" />
      <TextField v-model="form.email" label="Email" type="email" :error="err('organisation.email')" />
      <SelectField v-model="form.industry_id" label="Industry" :options="industries" placeholder="—" :error="err('industry_id')" />
      <TextArea v-model="form.description" label="Description" :error="err('description')" />
    </form>

    <template #footer-actions>
      <AppButton variant="secondary" @click="router.push(`/customers/${customerId}`)">Cancel</AppButton>
      <AppButton icon="check" :loading="saving" @click="save">Save</AppButton>
    </template>
  </ConsoleLayout>
</template>
