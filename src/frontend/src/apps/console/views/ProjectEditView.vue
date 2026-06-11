<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getLlmTonalities, getLlmTemperatures, getReleaseHistory } from '../../../shared/api.js';
import { useProjectsStore } from '../stores/projects.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TextField from '../components/TextField.vue';
import SelectField from '../components/SelectField.vue';
import TextArea from '../components/TextArea.vue';
import TokenField from '../components/TokenField.vue';
import AppButton from '../components/AppButton.vue';
import ReleaseNotesPanel from '../components/ReleaseNotesPanel.vue';

const route = useRoute();
const router = useRouter();
const store = useProjectsStore();
const { customerId, id } = route.params;

const LANGUAGES = [
  { value: 'en', label: 'English' },
  { value: 'de', label: 'German' },
  { value: 'fr', label: 'French' },
];

const tonalities = ref([]);
const temperatures = ref([]);
const errors = ref({});
const token = ref('');
const releaseNotes = ref([]);
const form = reactive({ name: '', description: '', language: 'en', llm_tonality_id: '', llm_temperature_id: '' });

onMounted(async () => {
  const [ton, temp, p] = await Promise.all([
    getLlmTonalities().then((r) => (tonalities.value = r.data.items.map((i) => ({ value: i.id, label: i.name })))),
    getLlmTemperatures().then((r) => (temperatures.value = r.data.items.map((i) => ({ value: i.id, label: `${i.name} (${i.value})` })))),
    store.fetchProject(customerId, id),
  ]);
  token.value = p.token || '';
  Object.assign(form, {
    name: p.name || '',
    description: p.description || '',
    language: p.language || 'en',
    llm_tonality_id: p.llm_tonality_id || '',
    llm_temperature_id: p.llm_temperature_id || '',
  });
  try {
    if (p.customer?.organisation_slug && p.key) {
      const res = await getReleaseHistory(p.customer.organisation_slug, p.key);
      releaseNotes.value = res.data.items || [];
    }
  } catch {
    /* ignore */
  }
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
    language: form.language,
    llm_tonality_id: form.llm_tonality_id,
    llm_temperature_id: form.llm_temperature_id,
  };
  try {
    await store.patchProject(customerId, id, payload);
    router.push(`/customers/${customerId}/projects/${id}`);
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {};
  }
}
</script>

<template>
  <ConsoleLayout :parent="{ label: 'Projects', to: '/projects' }" current="Edit Project">
    <form @submit.prevent="save">
      <TextField v-model="form.name" label="Name" required :error="err('name')" />
      <TokenField :token="token" />
      <SelectField v-model="form.language" label="Language" :options="LANGUAGES" :error="err('language')" />
      <SelectField v-model="form.llm_tonality_id" label="LLM tonality" :options="tonalities" placeholder="—" required :error="err('llm_tonality_id')" />
      <SelectField v-model="form.llm_temperature_id" label="LLM temperature" :options="temperatures" placeholder="—" required :error="err('llm_temperature_id')" />
      <TextArea v-model="form.description" label="Description" :error="err('description')" />

      <div class="flex justify-end gap-3 pt-6">
        <AppButton variant="secondary" @click="router.push(`/customers/${customerId}/projects/${id}`)">Cancel</AppButton>
        <AppButton type="submit" icon="check">Save</AppButton>
      </div>
    </form>

    <template #sidebar>
      <ReleaseNotesPanel :items="releaseNotes" />
    </template>
  </ConsoleLayout>
</template>
