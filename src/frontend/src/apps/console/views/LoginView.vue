<script setup>
import { ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import AuthCard from '../components/AuthCard.vue';
import AuthField from '../components/AuthField.vue';
import AppButton from '../components/AppButton.vue';
import Notification from '../components/Notification.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const username = ref('');
const password = ref('');
const notification = ref(null);
const loading = ref(false);

async function submit() {
  loading.value = true;
  notification.value = null;
  try {
    await auth.login(username.value, password.value);
    router.push(route.query.redirect || '/dashboard');
  } catch (e) {
    if (e.response?.status === 403) {
      notification.value = {
        type: 'warning',
        title: 'Account not activated',
        message: 'Please activate your account using the link we emailed you.',
      };
    } else {
      notification.value = {
        type: 'error',
        title: 'Login failed',
        message: 'The email or password you entered is incorrect.',
      };
    }
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <AuthCard>
    <Notification v-if="notification" v-bind="notification" class="mb-8" />

    <form @submit.prevent="submit">
      <AuthField
        v-model="username"
        label="E-Mail"
        type="email"
        autocomplete="username"
      />
      <AuthField
        v-model="password"
        label="Password"
        type="password"
        autocomplete="current-password"
        last
      />

      <div class="pt-16 flex justify-center">
        <AppButton type="submit" size="lg" :loading="loading">Login</AppButton>
      </div>
    </form>

    <p class="text-center text-[13px] text-meta mt-6">
      <RouterLink to="/register" class="underline hover:text-ink">Create account</RouterLink>
      <span> or </span>
      <RouterLink to="/forgot-password" class="underline hover:text-ink">forgot password?</RouterLink>
    </p>
  </AuthCard>
</template>
