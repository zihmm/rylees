<script setup>
import logoUrl from '../../../assets/logo.svg';
</script>

<template>
  <div class="min-h-screen bg-page flex items-center justify-center p-6">
    <div class="w-full max-w-[479px] bg-white rounded-card shadow-card p-10">
      <slot name="top" />
      <div class="flex justify-center mt-7 mb-16">
        <img :src="logoUrl" alt="Rylees" class="w-[170px] h-auto" />
      </div>
      <slot />
    </div>
  </div>
</template>
