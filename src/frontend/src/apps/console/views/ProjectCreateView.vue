<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getLlmTonalities, getLlmTemperatures } from '../../../shared/api.js';
import { useProjectsStore } from '../stores/projects.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TextField from '../components/TextField.vue';
import SelectField from '../components/SelectField.vue';
import TextArea from '../components/TextArea.vue';
import AppButton from '../components/AppButton.vue';

const route = useRoute();
const router = useRouter();
const store = useProjectsStore();
const customerId = route.params.customerId;

const LANGUAGES = [
  { value: 'en', label: 'English' },
  { value: 'de', label: 'German' },
  { value: 'fr', label: 'French' },
];

const tonalities = ref([]);
const temperatures = ref([]);
const errors = ref({});
const form = reactive({ name: '', description: '', language: 'en', llm_tonality_id: '', llm_temperature_id: '' });

onMounted(async () => {
  const [ton, temp] = await Promise.all([getLlmTonalities(), getLlmTemperatures()]);
  tonalities.value = ton.data.items.map((i) => ({ value: i.id, label: i.name }));
  temperatures.value = temp.data.items.map((i) => ({ value: i.id, label: `${i.name} (${i.value})` }));
});

function err(key) {
  const v = errors.value[key];
  return Array.isArray(v) ? v[0] : v || '';
}

async function save() {
  errors.value = {};
  const payload = {
    name: form.name,
    description: form.description,
    language: form.language, // new project field (backend-dependent)
    llm_tonality_id: form.llm_tonality_id,
    llm_temperature_id: form.llm_temperature_id,
  };
  try {
    const res = await store.storeProject(customerId, payload);
    router.push(`/customers/${customerId}/projects/${res.data.id}`);
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {};
  }
}
</script>

<template>
  <ConsoleLayout :parent="{ label: 'Organisations', to: '/customers' }" current="New Project">
    <form @submit.prevent="save">
      <TextField v-model="form.name" label="Name" required :error="err('name')" />
      <SelectField v-model="form.language" label="Language" :options="LANGUAGES" :error="err('language')" />
      <SelectField v-model="form.llm_tonality_id" label="LLM tonality" :options="tonalities" placeholder="—" required :error="err('llm_tonality_id')" />
      <SelectField v-model="form.llm_temperature_id" label="LLM temperature" :options="temperatures" placeholder="—" required :error="err('llm_temperature_id')" />
      <TextArea v-model="form.description" label="Description" :error="err('description')" />

      <div class="flex justify-end gap-3 pt-6">
        <AppButton variant="secondary" @click="router.push(`/customers/${customerId}`)">Cancel</AppButton>
        <AppButton type="submit" icon="check">Save</AppButton>
      </div>
    </form>
  </ConsoleLayout>
</template>
