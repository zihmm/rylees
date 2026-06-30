<script setup>
import { reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { register } from '../../../shared/api.js';
import AuthCard from '../components/AuthCard.vue';
import AuthField from '../components/AuthField.vue';
import AppButton from '../components/AppButton.vue';
import Notification from '../components/Notification.vue';
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
const notification = ref(null);
const registered = ref(false);
const loading = ref(false);

const accountFields = [
  { k: 'firstname', label: 'Firstname', err: ['profile.firstname'] },
  { k: 'lastname', label: 'Lastname', err: ['profile.lastname'] },
  { k: 'username', label: 'E-Mail', type: 'email', err: ['username'] },
  { k: 'password', label: 'Password', type: 'password', err: ['password'] },
  { k: 'confirm_password', label: 'Confirm password', type: 'password', err: ['confirm_password'] },
];

// Split around the postcode + city row, which is rendered side by side below.
const organisationFieldsTop = [
  { k: 'org_name', label: 'Name', err: ['organisation.name'] },
  { k: 'org_street', label: 'Street', err: ['organisation.street'] },
];
const organisationFieldsBottom = [
  { k: 'org_website', label: 'Website', err: ['organisation.website'] },
  { k: 'org_email', label: 'Email', type: 'email', err: ['organisation.email'] },
];

async function submit() {
  fieldErrors.value = {};
  notification.value = null;
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
    registered.value = true;
    notification.value = {
      type: 'success',
      title: 'Success',
      message: 'Your account was created. Please activate it with the activation link.',
    };
  } catch (e) {
    if (e.response?.status === 422) {
      fieldErrors.value = e.response.data.errors || {};
      notification.value = {
        type: 'warning',
        title: 'Please check your input',
        message: 'Some fields need your attention before we can create your account.',
      };
    } else {
      notification.value = {
        type: 'error',
        title: 'Registration failed',
        message: 'Something went wrong creating your account. Please try again.',
      };
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

    <Notification v-if="notification" v-bind="notification" class="mb-8" />

    <div v-if="registered" class="text-center">
      <RouterLink to="/login" class="inline-block text-accent underline">Go to login</RouterLink>
    </div>

    <form v-else @submit.prevent="submit">
      <h2 class="text-[17px] font-semibold text-black mb-6">Register Account</h2>

      <p class="text-[13px] font-medium text-meta tracking-wide mt-2 mb-1">ACCOUNT</p>
      <AuthField
        v-for="(f, i) in accountFields"
        :key="f.k"
        v-model="form[f.k]"
        :label="f.label"
        :type="f.type || 'text'"
        :error="fieldErrors[f.k] || apiError(...(f.err || []))"
        :last="i === accountFields.length - 1"
      />

      <p class="text-[13px] font-medium text-meta tracking-wide pt-6 mb-1">ORGANISATION</p>
      <AuthField
        v-for="f in organisationFieldsTop"
        :key="f.k"
        v-model="form[f.k]"
        :label="f.label"
        :type="f.type || 'text'"
        :error="apiError(...(f.err || []))"
      />
      <div class="flex gap-4">
        <div class="w-[120px] shrink-0">
          <AuthField
            v-model="form.org_postcode"
            label="Postcode"
            :error="apiError('organisation.postcode')"
          />
        </div>
        <div class="flex-1 min-w-0">
          <AuthField
            v-model="form.org_city"
            label="City"
            :error="apiError('organisation.city')"
          />
        </div>
      </div>
      <AuthField
        v-for="(f, i) in organisationFieldsBottom"
        :key="f.k"
        v-model="form[f.k]"
        :label="f.label"
        :type="f.type || 'text'"
        :error="apiError(...(f.err || []))"
        :last="i === organisationFieldsBottom.length - 1"
      />

      <div class="pt-8 flex justify-center">
        <AppButton type="submit" size="lg" :loading="loading">Register</AppButton>
      </div>
    </form>
  </AuthCard>
</template>
