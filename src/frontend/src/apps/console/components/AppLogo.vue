<script setup>
import sujetUrl from '../../../assets/logo-sujet.svg';
defineProps({ size: { type: Number, default: 36 } });
</script>

<template>
  <img :src="sujetUrl" alt="Rylees" class="w-auto select-none" :style="{ height: size + 'px' }" />
</template>
