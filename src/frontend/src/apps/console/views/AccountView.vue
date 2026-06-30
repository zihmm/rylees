<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { updateMe } from '../../../shared/api.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TextField from '../components/TextField.vue';
import PostcodeCityField from '../components/PostcodeCityField.vue';
import AppButton from '../components/AppButton.vue';

const auth = useAuthStore();

const errors = ref({});
const successMessage = ref(null);
const saving = ref(false);
const form = reactive({
  firstname: '', lastname: '',
  org_name: '', org_street: '', org_postcode: '', org_city: '', org_website: '', org_email: '',
  current_password: '', new_password: '', confirm_new_password: '',
});

onMounted(() => {
  const u = auth.user || {};
  const p = u.profile || {};
  const o = u.organisation || {};
  Object.assign(form, {
    firstname: p.firstname || '', lastname: p.lastname || '',
    org_name: o.name || '', org_street: o.street || '', org_postcode: o.postcode || '',
    org_city: o.city || '', org_website: o.website || '', org_email: o.email || '',
  });
});

function err(key) {
  const v = errors.value[key];
  return Array.isArray(v) ? v[0] : v || '';
}

async function save() {
  errors.value = {};
  successMessage.value = null;

  // Client-side password validation (before API call).
  if (form.new_password) {
    if (form.new_password !== form.confirm_new_password) {
      errors.value.confirm_new_password = 'Passwords do not match';
      return;
    }
    if (!form.current_password) {
      errors.value.current_password = 'Current password is required to change your password';
      return;
    }
  }

  const payload = {
    profile: { firstname: form.firstname, lastname: form.lastname },
    organisation: {
      name: form.org_name, street: form.org_street, postcode: form.org_postcode,
      city: form.org_city, website: form.org_website, email: form.org_email,
    },
  };
  if (form.new_password) {
    payload.current_password = form.current_password;
    payload.new_password = form.new_password;
  }

  saving.value = true;
  try {
    const res = await updateMe(payload);
    auth.updateUser(res.data);
    successMessage.value = 'Profile updated successfully.';
    form.current_password = form.new_password = form.confirm_new_password = '';
  } catch (e) {
    if (e.response?.status === 422) {
      const apiErrors = e.response.data.errors || {};
      errors.value = apiErrors;
      if (apiErrors.current_password) {
        errors.value.current_password = 'Current password is incorrect.';
      }
    }
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <ConsoleLayout current="Profile">
    <form @submit.prevent="save">
      <p v-if="successMessage" class="mb-4 text-[14px] text-green-600">{{ successMessage }}</p>

      <p class="text-[13px] font-medium text-meta tracking-wide pb-2">PROFILE</p>
      <TextField v-model="form.firstname" label="Firstname" :error="err('profile.firstname')" />
      <TextField v-model="form.lastname" label="Lastname" :error="err('profile.lastname')" />

      <p class="text-[13px] font-medium text-meta tracking-wide pt-8 pb-2">ORGANISATION</p>
      <TextField v-model="form.org_name" label="Name" :error="err('organisation.name')" />
      <TextField v-model="form.org_street" label="Street" :error="err('organisation.street')" />
      <PostcodeCityField
        v-model:postcode="form.org_postcode"
        v-model:city="form.org_city"
        :postcode-error="err('organisation.postcode')"
        :city-error="err('organisation.city')"
      />
      <TextField v-model="form.org_website" label="Website" :error="err('organisation.website')" />
      <TextField v-model="form.org_email" label="Email" type="email" :error="err('organisation.email')" />

      <p class="text-[13px] font-medium text-meta tracking-wide pt-8 pb-2">CHANGE PASSWORD</p>
      <TextField v-model="form.current_password" label="Current password" type="password" :error="err('current_password')" />
      <TextField v-model="form.new_password" label="New password" type="password" :error="err('new_password')" />
      <TextField v-model="form.confirm_new_password" label="Confirm new password" type="password" :error="err('confirm_new_password')" />
    </form>

    <template #footer-actions>
      <AppButton icon="check" :loading="saving" @click="save">Save</AppButton>
    </template>
  </ConsoleLayout>
</template>
