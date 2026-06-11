<script setup>
import ConsoleLayout from '../components/ConsoleLayout.vue';
import art from '../../../assets/illustrations/error-500.svg';
</script>

<template>
  <ConsoleLayout current="Server Error">
    <div class="flex flex-col items-center justify-center text-center py-20">
      <img :src="art" alt="" class="w-64 h-64 object-contain mb-8" />
      <p class="text-[15px] text-meta max-w-sm">Ooh ooh. We have a Server Error. Happens to the best of us.</p>
    </div>
  </ConsoleLayout>
</template>
