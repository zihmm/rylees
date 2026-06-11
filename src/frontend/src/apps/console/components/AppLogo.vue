<script setup>
import AppIcon from '../../../shared/icons/AppIcon.vue';
defineProps({ size: { type: Number, default: 36 } });
</script>

<template>
  <div class="flex items-center gap-2 select-none">
    <AppIcon name="bolt" :size="size" class="text-accent" />
    <span class="font-bold tracking-tight text-[#37424a]" :style="{ fontSize: size * 0.72 + 'px' }">Rylees</span>
  </div>
</template>
