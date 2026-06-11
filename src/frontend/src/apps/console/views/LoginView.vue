<script setup>
import { ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import AuthCard from '../components/AuthCard.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const username = ref('');
const password = ref('');
const error = ref(null);
const loading = ref(false);

async function submit() {
  loading.value = true;
  error.value = null;
  try {
    await auth.login(username.value, password.value);
    router.push(route.query.redirect || '/dashboard');
  } catch (e) {
    const status = e.response?.status;
    if (status === 403) {
      error.value = 'Account not yet activated. Please check your email.';
    } else {
      error.value = 'Invalid email or password.';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <AuthCard>
    <form class="space-y-1" @submit.prevent="submit">
      <input
        v-model="username"
        type="email"
        placeholder="E-Mail"
        autocomplete="username"
        class="w-full py-3 border-b border-field-border text-[15px] outline-none focus:border-accent"
      />
      <input
        v-model="password"
        type="password"
        placeholder="Password"
        autocomplete="current-password"
        class="w-full py-3 border-b border-field-border text-[15px] outline-none focus:border-accent"
      />

      <p v-if="error" class="text-danger text-[13px] pt-3">{{ error }}</p>

      <div class="pt-8 flex justify-center">
        <button
          type="submit"
          :disabled="loading"
          class="bg-accent text-white font-medium rounded-field px-10 h-12 disabled:opacity-60"
        >
          {{ loading ? '…' : 'Login' }}
        </button>
      </div>
    </form>

    <p class="text-center text-[13px] text-meta mt-6">
      <RouterLink to="/register" class="underline hover:text-ink">Create account</RouterLink>
      <span> or </span>
      <span class="underline cursor-default" title="Not available">forgot password?</span>
    </p>
  </AuthCard>
</template>
