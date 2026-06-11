<script setup>
import { RouterLink } from 'vue-router';
import ConsoleLayout from '../components/ConsoleLayout.vue';
import art from '../../../assets/illustrations/error-404.svg';
</script>

<template>
  <ConsoleLayout current="Error 404">
    <div class="flex flex-col items-center justify-center text-center py-20">
      <img :src="art" alt="" class="w-56 h-56 object-contain mb-8" />
      <p class="text-[15px]"><span class="font-semibold">404 -</span> <span class="text-meta">We can't find your page</span></p>
      <RouterLink to="/dashboard" class="mt-3 text-accent">← Go back to dashboard</RouterLink>
    </div>
  </ConsoleLayout>
</template>
