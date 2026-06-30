<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { getIndustries } from '../../../shared/api.js';
import { useCustomersStore } from '../stores/customers.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TextField from '../components/TextField.vue';
import PostcodeCityField from '../components/PostcodeCityField.vue';
import SelectField from '../components/SelectField.vue';
import TextArea from '../components/TextArea.vue';
import AppButton from '../components/AppButton.vue';

const router = useRouter();
const store = useCustomersStore();

const industries = ref([]);
const errors = ref({});
const saving = ref(false);
const form = reactive({
  name: '', street: '', postcode: '', city: '', website: '', email: '',
  industry_id: '', description: '',
  contact_firstname: '', contact_lastname: '', contact_email: '',
});

onMounted(async () => {
  const res = await getIndustries();
  industries.value = res.data.items.map((i) => ({ value: i.id, label: i.name }));
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
    description: form.description,
  };
  if (form.industry_id) payload.industry_id = form.industry_id;
  if (form.contact_firstname || form.contact_lastname || form.contact_email) {
    payload.main_contact = {
      firstname: form.contact_firstname,
      lastname: form.contact_lastname,
      email: form.contact_email,
    };
  }
  saving.value = true;
  try {
    const res = await store.storeCustomer(payload);
    router.push(`/customers/${res.data.id}`);
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {};
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <ConsoleLayout :parent="{ label: 'Customers', to: '/customers' }" current="New Customer">
    <form @submit.prevent="save">
      <TextField v-model="form.name" label="Name" required :error="err('organisation.name')" />
      <TextField v-model="form.street" label="Street" :error="err('organisation.street')" />
      <PostcodeCityField
        v-model:postcode="form.postcode"
        v-model:city="form.city"
        :postcode-error="err('organisation.postcode')"
        :city-error="err('organisation.city')"
      />
      <TextField v-model="form.website" label="Website" :error="err('organisation.website')" />
      <TextField v-model="form.email" label="Email" type="email" :error="err('organisation.email')" />
      <SelectField v-model="form.industry_id" label="Industry" :options="industries" placeholder="—" :error="err('industry_id')" />
      <TextArea v-model="form.description" label="Description" :error="err('description')" />

      <p class="text-[13px] font-medium text-meta tracking-wide pt-8 pb-2">MAIN CONTACT (optional)</p>
      <TextField v-model="form.contact_firstname" label="Firstname" :error="err('main_contact.firstname')" />
      <TextField v-model="form.contact_lastname" label="Lastname" :error="err('main_contact.lastname')" />
      <TextField v-model="form.contact_email" label="Email" type="email" :error="err('main_contact.email')" />
    </form>

    <template #footer-actions>
      <AppButton variant="secondary" @click="router.push('/customers')">Cancel</AppButton>
      <AppButton icon="check" :loading="saving" @click="save">Save</AppButton>
    </template>
  </ConsoleLayout>
</template>
