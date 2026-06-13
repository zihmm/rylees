<script setup>
import { ref, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { resetPassword } from '../../../shared/api.js';
import AuthCard from '../components/AuthCard.vue';
import AuthField from '../components/AuthField.vue';
import AppButton from '../components/AppButton.vue';
import Notification from '../components/Notification.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

const route = useRoute();
const token = computed(() => route.query.token || '');

const password = ref('');
const confirmPassword = ref('');
const fieldErrors = ref({});
const notification = ref(null);
const done = ref(false);
const loading = ref(false);

async function submit() {
  fieldErrors.value = {};
  notification.value = null;

  // Client-side guards mirror the API rules (min 12 chars) before the round trip.
  if (password.value.length < 12) {
    fieldErrors.value.password = 'Password must be at least 12 characters.';
    return;
  }
  if (password.value !== confirmPassword.value) {
    fieldErrors.value.confirm_password = 'Passwords do not match.';
    return;
  }

  loading.value = true;
  try {
    await resetPassword(token.value, password.value);
    done.value = true;
    notification.value = {
      type: 'success',
      title: 'Password reset',
      message: 'Your password has been reset. You can now log in with your new password.',
    };
  } catch (e) {
    if (e.response?.status === 404) {
      notification.value = {
        type: 'error',
        title: 'Link expired',
        message: 'This reset link is invalid or has expired. Please request a new one.',
      };
    } else if (e.response?.status === 422) {
      fieldErrors.value = e.response.data.errors || {};
    } else {
      notification.value = {
        type: 'error',
        title: 'Something went wrong',
        message: 'We could not reset your password. Please try again.',
      };
    }
  } finally {
    loading.value = false;
  }
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

    <div v-if="done" class="text-center">
      <RouterLink to="/login" class="inline-block text-accent underline">Go to login</RouterLink>
    </div>

    <div v-else-if="!token" class="text-center py-6">
      <p class="text-[15px] text-danger">This reset link is missing its token.</p>
      <RouterLink to="/forgot-password" class="inline-block mt-6 text-accent underline">
        Request a new link
      </RouterLink>
    </div>

    <form v-else @submit.prevent="submit">
      <h2 class="text-[17px] font-semibold text-black mb-2">Reset password</h2>
      <p class="text-[13px] text-meta mb-6">Choose a new password for your account.</p>

      <AuthField
        v-model="password"
        label="New password"
        type="password"
        autocomplete="new-password"
        :error="fieldErrors.password"
      />
      <AuthField
        v-model="confirmPassword"
        label="Confirm new password"
        type="password"
        autocomplete="new-password"
        :error="fieldErrors.confirm_password"
        last
      />

      <div class="pt-16 flex justify-center">
        <AppButton type="submit" size="lg" :loading="loading">Reset password</AppButton>
      </div>
    </form>
  </AuthCard>
</template>
