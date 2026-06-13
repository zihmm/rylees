<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useCustomersStore } from '../stores/customers.js';
import { createContact, updateContact, deleteContact } from '../../../shared/api.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import AppButton from '../components/AppButton.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

const route = useRoute();
const router = useRouter();
const store = useCustomersStore();

const customer = computed(() => store.currentCustomer);
const customerId = route.params.id;

const adding = ref(false);
const editingId = ref(null);
const submitting = ref(false);
const blank = () => ({ firstname: '', lastname: '', email: '' });
const draft = reactive(blank());

onMounted(() => store.fetchCustomer(customerId));

function startAdd() {
  Object.assign(draft, blank());
  editingId.value = null;
  adding.value = true;
}
function startEdit(c) {
  Object.assign(draft, { firstname: c.firstname, lastname: c.lastname, email: c.email });
  adding.value = false;
  editingId.value = c.id;
}
async function reload() {
  await store.fetchCustomer(customerId);
  adding.value = false;
  editingId.value = null;
}
async function submitAdd() {
  submitting.value = true;
  try {
    await createContact(customerId, { ...draft });
    await reload();
  } finally {
    submitting.value = false;
  }
}
async function submitEdit() {
  submitting.value = true;
  try {
    await updateContact(customerId, editingId.value, { ...draft });
    await reload();
  } finally {
    submitting.value = false;
  }
}
async function removeContact(id) {
  if (!confirm('Delete this contact?')) return;
  await deleteContact(customerId, id);
  await reload();
}
</script>

<template>
  <ConsoleLayout
    v-if="customer"
    :parent="{ label: 'Customers', to: '/customers' }"
    :current="customer.organisation?.name || ''"
  >
    <!-- Info panel -->
    <section class="rounded-card border border-field-border p-6 mb-8">
      <div class="flex items-start justify-between">
        <div>
          <h2 class="text-lg font-semibold text-black">{{ customer.organisation?.name }}</h2>
          <p class="text-meta text-[13px]">{{ customer.organisation?.slug }}</p>
        </div>
        <AppButton variant="secondary" @click="router.push(`/customers/${customerId}/edit`)">Edit organisation</AppButton>
      </div>
      <dl class="grid grid-cols-2 gap-x-8 gap-y-2 mt-4 text-[14px]">
        <div><dt class="text-meta text-[12px]">Industry</dt><dd>{{ customer.industry?.name || '—' }}</dd></div>
        <div><dt class="text-meta text-[12px]">City</dt><dd>{{ customer.organisation?.city || '—' }}</dd></div>
        <div><dt class="text-meta text-[12px]">Website</dt><dd>{{ customer.organisation?.website || '—' }}</dd></div>
        <div><dt class="text-meta text-[12px]">Email</dt><dd>{{ customer.organisation?.email || '—' }}</dd></div>
      </dl>
    </section>

    <!-- Contacts -->
    <section class="mb-8">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-[15px] font-semibold">Contacts</h3>
        <button class="text-accent text-[13px] flex items-center gap-1" @click="startAdd"><AppIcon name="plus" :size="14" /> Add contact</button>
      </div>

      <ul class="divide-y divide-field-border border border-field-border rounded-card">
        <li v-for="c in customer.contacts" :key="c.id" class="p-4">
          <form v-if="editingId === c.id" class="flex flex-wrap items-center gap-2" @submit.prevent="submitEdit">
            <input v-model="draft.firstname" placeholder="Firstname" class="h-9 border border-field-border rounded-field px-2 text-[14px]" />
            <input v-model="draft.lastname" placeholder="Lastname" class="h-9 border border-field-border rounded-field px-2 text-[14px]" />
            <input v-model="draft.email" placeholder="Email" class="h-9 border border-field-border rounded-field px-2 text-[14px]" />
            <AppButton type="submit" icon="check" :loading="submitting">Save</AppButton>
            <AppButton variant="secondary" @click="editingId = null">Cancel</AppButton>
          </form>
          <div v-else class="flex items-center justify-between">
            <div class="text-[14px]">
              <span class="font-medium">{{ c.firstname }} {{ c.lastname }}</span>
              <span class="text-meta"> · {{ c.email }}</span>
              <span v-if="c.id === customer.main_contact?.id" class="ml-2 text-[11px] text-accent font-medium">MAIN</span>
            </div>
            <div class="flex gap-3 text-[13px]">
              <button class="text-meta hover:text-ink" @click="startEdit(c)">Edit</button>
              <button class="text-danger" @click="removeContact(c.id)">Delete</button>
            </div>
          </div>
        </li>

        <li v-if="adding" class="p-4">
          <form class="flex flex-wrap items-center gap-2" @submit.prevent="submitAdd">
            <input v-model="draft.firstname" placeholder="Firstname" class="h-9 border border-field-border rounded-field px-2 text-[14px]" />
            <input v-model="draft.lastname" placeholder="Lastname" class="h-9 border border-field-border rounded-field px-2 text-[14px]" />
            <input v-model="draft.email" placeholder="Email" class="h-9 border border-field-border rounded-field px-2 text-[14px]" />
            <AppButton type="submit" icon="check" :loading="submitting">Save</AppButton>
            <AppButton variant="secondary" @click="adding = false">Cancel</AppButton>
          </form>
        </li>
        <li v-if="!customer.contacts?.length && !adding" class="p-4 text-meta text-[14px]">No contacts.</li>
      </ul>
    </section>

    <!-- Projects -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-[15px] font-semibold">Projects</h3>
        <AppButton icon="plus" @click="router.push(`/customers/${customerId}/projects/new`)">Add Project</AppButton>
      </div>
      <ul class="border border-field-border rounded-card divide-y divide-field-border">
        <li v-for="p in customer.projects" :key="p.id">
          <RouterLink :to="`/customers/${customerId}/projects/${p.id}`" class="flex items-center justify-between p-4 hover:bg-panel">
            <span class="text-[14px] font-medium">{{ p.name }}</span>
            <span class="text-meta text-[13px]">{{ p.key }}</span>
          </RouterLink>
        </li>
        <li v-if="!customer.projects?.length" class="p-4 text-meta text-[14px]">No projects yet.</li>
      </ul>
    </section>
  </ConsoleLayout>
</template>
