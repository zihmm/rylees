<script setup>
import { useAuthStore } from '../stores/auth.js';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import TokenField from '../components/TokenField.vue';

const auth = useAuthStore();
</script>

<template>
  <ConsoleLayout current="API Key">
    <p class="text-[13px] font-medium text-meta tracking-wide pb-2">API KEY</p>
    <TokenField label="API Key" :token="auth.user?.api_key || ''" helper="Use this key in your CLI .env" />
  </ConsoleLayout>
</template>
