<script setup>
import { reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { register } from '../../../shared/api.js';
import AuthCard from '../components/AuthCard.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

const form = reactive({
  firstname: '',
  lastname: '',
  username: '',
  password: '',
  confirm_password: '',
  org_name: '',
  org_street: '',
  org_city: '',
  org_postcode: '',
  org_website: '',
  org_email: '',
});

const fieldErrors = ref({});
const successMessage = ref(null);
const loading = ref(false);

function fieldClass(key) {
  return fieldErrors.value[key]
    ? 'border-danger'
    : 'border-field-border focus:border-accent';
}

async function submit() {
  fieldErrors.value = {};
  // Client-side: password confirmation.
  if (form.password !== form.confirm_password) {
    fieldErrors.value.confirm_password = 'Passwords do not match';
    return;
  }
  loading.value = true;
  const payload = {
    username: form.username,
    password: form.password,
    profile: { firstname: form.firstname, lastname: form.lastname },
    organisation: {
      name: form.org_name,
      street: form.org_street,
      city: form.org_city,
      postcode: form.org_postcode,
      website: form.org_website,
      email: form.org_email,
    },
  };
  try {
    await register(payload);
    successMessage.value = 'Account created. Please check your email to activate your account.';
  } catch (e) {
    if (e.response?.status === 422) {
      fieldErrors.value = e.response.data.errors || {};
    }
  } finally {
    loading.value = false;
  }
}

// Map dot-notation API error keys (profile.firstname, organisation.name) to local fields.
function apiError(...keys) {
  for (const k of keys) {
    if (fieldErrors.value[k]) {
      return Array.isArray(fieldErrors.value[k]) ? fieldErrors.value[k][0] : fieldErrors.value[k];
    }
  }
  return '';
}
</script>

<template>
  <AuthCard>
    <template #top>
      <RouterLink to="/login" class="flex items-center gap-1 text-[13px] text-meta hover:text-ink mb-2">
        <AppIcon name="chevron-left" :size="14" /> Back to login
      </RouterLink>
    </template>

    <div v-if="successMessage" class="text-center py-6">
      <p class="text-[15px] text-ink">{{ successMessage }}</p>
      <RouterLink to="/login" class="inline-block mt-6 text-accent underline">Go to login</RouterLink>
    </div>

    <form v-else class="space-y-1" @submit.prevent="submit">
      <h2 class="text-[17px] font-semibold text-black mb-6">Register Account</h2>

      <p class="text-[13px] font-medium text-meta tracking-wide mt-2">ACCOUNT</p>
      <template v-for="f in [
        { k: 'firstname', ph: 'Firstname', err: ['profile.firstname'] },
        { k: 'lastname', ph: 'Lastname', err: ['profile.lastname'] },
        { k: 'username', ph: 'E-Mail', type: 'email', err: ['username'] },
        { k: 'password', ph: 'Password', type: 'password', err: ['password'] },
        { k: 'confirm_password', ph: 'Confirm password', type: 'password', err: ['confirm_password'] },
      ]" :key="f.k">
        <input
          v-model="form[f.k]"
          :type="f.type || 'text'"
          :placeholder="f.ph"
          class="w-full py-3 border-b text-[15px] outline-none"
          :class="(apiError(...(f.err || [])) || fieldErrors[f.k]) ? 'border-danger' : 'border-field-border focus:border-accent'"
        />
        <p v-if="apiError(...(f.err || [])) || fieldErrors[f.k]" class="text-danger text-[11px] pb-1">
          {{ fieldErrors[f.k] || apiError(...(f.err || [])) }}
        </p>
      </template>

      <p class="text-[13px] font-medium text-meta tracking-wide pt-4">ORGANISATION</p>
      <template v-for="f in [
        { k: 'org_name', ph: 'Name', err: ['organisation.name'] },
        { k: 'org_street', ph: 'Street', err: ['organisation.street'] },
        { k: 'org_city', ph: 'City', err: ['organisation.city'] },
        { k: 'org_postcode', ph: 'Postcode', err: ['organisation.postcode'] },
        { k: 'org_website', ph: 'Website', err: ['organisation.website'] },
        { k: 'org_email', ph: 'Email', type: 'email', err: ['organisation.email'] },
      ]" :key="f.k">
        <input
          v-model="form[f.k]"
          :type="f.type || 'text'"
          :placeholder="f.ph"
          class="w-full py-3 border-b text-[15px] outline-none"
          :class="apiError(...(f.err || [])) ? 'border-danger' : 'border-field-border focus:border-accent'"
        />
        <p v-if="apiError(...(f.err || []))" class="text-danger text-[11px] pb-1">{{ apiError(...(f.err || [])) }}</p>
      </template>

      <div class="pt-8 flex justify-center">
        <button type="submit" :disabled="loading" class="bg-accent text-white font-medium rounded-field px-10 h-12 disabled:opacity-60">
          {{ loading ? '…' : 'Register' }}
        </button>
      </div>
    </form>
  </AuthCard>
</template>
