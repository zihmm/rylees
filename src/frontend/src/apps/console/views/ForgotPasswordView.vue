<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { forgotPassword } from '../../../shared/api.js';
import AuthCard from '../components/AuthCard.vue';
import AuthField from '../components/AuthField.vue';
import AppButton from '../components/AppButton.vue';
import Notification from '../components/Notification.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

const username = ref('');
const fieldError = ref('');
const notification = ref(null);
const submitted = ref(false);
const loading = ref(false);

async function submit() {
  fieldError.value = '';
  notification.value = null;
  loading.value = true;
  try {
    await forgotPassword(username.value);
    // The API responds identically whether or not the address exists (no enumeration).
    submitted.value = true;
    notification.value = {
      type: 'success',
      title: 'Check your inbox',
      message: 'If an account exists for this email, a password reset link has been sent.',
    };
  } catch (e) {
    if (e.response?.status === 422) {
      fieldError.value = e.response.data.errors?.username || 'Please enter a valid email address.';
    } else {
      notification.value = {
        type: 'error',
        title: 'Something went wrong',
        message: 'We could not process your request. Please try again.',
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

    <div v-if="submitted" class="text-center">
      <RouterLink to="/login" class="inline-block text-accent underline">Back to login</RouterLink>
    </div>

    <form v-else @submit.prevent="submit">
      <h2 class="text-[17px] font-semibold text-black mb-2">Forgot password</h2>
      <p class="text-[13px] text-meta mb-6">
        Enter your email and we'll send you a link to reset your password.
      </p>

      <AuthField
        v-model="username"
        label="E-Mail"
        type="email"
        autocomplete="username"
        :error="fieldError"
        last
      />

      <div class="pt-16 flex justify-center">
        <AppButton type="submit" size="lg" :loading="loading">Send reset link</AppButton>
      </div>
    </form>
  </AuthCard>
</template>
