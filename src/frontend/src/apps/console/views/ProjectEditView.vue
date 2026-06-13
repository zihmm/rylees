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
const saving = ref(false);
const token = ref('');
const projectName = ref('');
const releaseNotes = ref([]);
const form = reactive({ name: '', description: '', language: 'en', llm_tonality_id: '', llm_temperature_id: '' });

onMounted(async () => {
  const [, , p] = await Promise.all([
    getLlmTonalities().then((r) => (tonalities.value = r.data.items.map((i) => ({ value: i.id, label: i.name, rawName: i.name })))),
    getLlmTemperatures().then((r) => (temperatures.value = r.data.items.map((i) => ({ value: i.id, label: `${i.name} (${i.value})`, rawName: i.name, rawValue: i.value })))),
    store.fetchProject(customerId, id),
  ]);
  // GET project returns llm.{tonality,temperature} (names/values), not the *_id fields —
  // match them back to the loaded reference data to preselect the dropdowns.
  const matchedTonality = tonalities.value.find((t) => t.rawName === p.llm?.tonality);
  const matchedTemperature = temperatures.value.find(
    (t) => t.rawName === p.llm?.temperature || String(t.rawValue) === String(p.llm?.temperature)
  );
  token.value = p.token || '';
  projectName.value = p.name || '';
  Object.assign(form, {
    name: p.name || '',
    description: p.description || '',
    language: p.language || 'en',
    llm_tonality_id: p.llm_tonality_id || matchedTonality?.value || '',
    llm_temperature_id: p.llm_temperature_id || matchedTemperature?.value || '',
  });
  try {
    if (p.customer?.organisation_slug && p.key) {
      const res = await getReleaseHistory(p.customer.organisation_slug, p.key);
      releaseNotes.value = res.data.items || [];
    }
  } catch {
    /* public history may be unavailable */
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
    language: form.language, // new project field (backend-dependent, DESIGN-SPEC-DL §11.4)
    llm_tonality_id: form.llm_tonality_id,
    llm_temperature_id: form.llm_temperature_id,
  };
  saving.value = true;
  try {
    await store.patchProject(customerId, id, payload);
    router.push('/projects');
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {};
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <ConsoleLayout :parent="{ label: 'Projects', to: '/projects' }" :current="projectName">
    <form @submit.prevent="save">
      <TextField v-model="form.name" label="Name" required :error="err('name')" />
      <TokenField :token="token" />
      <SelectField v-model="form.language" label="Language" :options="LANGUAGES" :error="err('language')" />
      <SelectField v-model="form.llm_tonality_id" label="LLM tonality" :options="tonalities" placeholder="—" required :error="err('llm_tonality_id')" />
      <SelectField v-model="form.llm_temperature_id" label="LLM temperature" :options="temperatures" placeholder="—" required :error="err('llm_temperature_id')" />
      <TextArea v-model="form.description" label="Description" :error="err('description')" />
    </form>

    <template #sidebar>
      <ReleaseNotesPanel :items="releaseNotes" />
    </template>

    <template #footer-actions>
      <AppButton variant="secondary" @click="router.push('/projects')">Cancel</AppButton>
      <AppButton icon="check" :loading="saving" @click="save">Save</AppButton>
    </template>
  </ConsoleLayout>
</template>
