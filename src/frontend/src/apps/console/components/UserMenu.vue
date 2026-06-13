<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import AppIcon from '../../../shared/icons/AppIcon.vue';

const auth = useAuthStore();
const router = useRouter();
const open = ref(false);

async function logout() {
  open.value = false;
  await auth.logout();
  router.push({ name: 'login' });
}
function go(name) {
  open.value = false;
  router.push({ name });
}
</script>

<template>
  <div class="relative">
    <!-- Click-away backdrop -->
    <div v-if="open" class="fixed inset-0 z-10" @click="open = false" />

    <div
      v-if="open"
      class="absolute bottom-12 right-2 z-20 w-40 bg-white rounded-card shadow-card border border-field-border overflow-hidden"
    >
      <button class="flex items-center gap-2 w-full px-4 py-2.5 text-[14px] text-ink hover:bg-panel" @click="go('api-key')">
        <AppIcon name="key" :size="16" class="text-helper" /> API Key
      </button>
      <button class="flex items-center gap-2 w-full px-4 py-2.5 text-[14px] text-ink hover:bg-panel" @click="go('account')">
        <AppIcon name="user" :size="16" class="text-helper" /> Profile
      </button>
      <div class="border-t border-field-border" />
      <button class="flex items-center gap-2 w-full px-4 py-2.5 text-[14px] text-ink hover:bg-panel" @click="logout">
        Logout
      </button>
    </div>

    <div class="flex items-center gap-3 w-full px-2 py-2 text-muted">
      <AppIcon name="user" :size="18" />
      <span class="flex-1 text-left text-[15px] truncate">{{ auth.user?.profile?.firstname || 'Account' }}</span>
      <button class="shrink-0 -m-1 p-1 hover:text-ink" aria-label="Account menu" @click="open = !open">
        <AppIcon name="kebab" :size="16" />
      </button>
    </div>
  </div>
</template>
