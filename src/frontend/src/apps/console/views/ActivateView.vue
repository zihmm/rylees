<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { activate } from '../../../shared/api.js';
import AuthCard from '../components/AuthCard.vue';

const route = useRoute();
const state = ref('loading'); // 'loading' | 'success' | 'error'

onMounted(async () => {
  try {
    await activate(route.query.token);
    state.value = 'success';
  } catch {
    state.value = 'error';
  }
});
</script>

<template>
  <AuthCard>
    <div class="text-center py-6">
      <p v-if="state === 'loading'" class="text-meta">Activating your account…</p>

      <template v-else-if="state === 'success'">
        <p class="text-[15px] text-ink">Your account has been activated.</p>
        <RouterLink to="/login" class="inline-block mt-6 text-accent underline">Go to login</RouterLink>
      </template>

      <template v-else>
        <p class="text-[15px] text-danger">Activation link is invalid or has already been used.</p>
        <RouterLink to="/login" class="inline-block mt-6 text-accent underline">Back to login</RouterLink>
      </template>
    </div>
  </AuthCard>
</template>
