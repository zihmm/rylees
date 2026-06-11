<script setup>
defineProps({
  image: { type: [String, Object], default: '' },
  caption: { type: String, default: '' },
});
</script>

<template>
  <div class="flex flex-col items-center text-center px-6">
    <img v-if="image" :src="image" alt="" class="w-40 h-40 object-contain mb-6" />
    <p v-if="caption" class="text-meta mb-5">{{ caption }}</p>
    <slot />
  </div>
</template>
