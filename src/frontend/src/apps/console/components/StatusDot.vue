<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: { type: String, default: 'green' }, // green | rose | amber | gray
});

// Status dot: a solid inner circle inside a lighter outer halo ring.
const VARIANTS = {
  green: { inner: 'bg-green-500', outer: 'bg-green-500/25' },
  rose: { inner: 'bg-rose-500', outer: 'bg-rose-500/25' },
  amber: { inner: 'bg-accent', outer: 'bg-accent/25' },
  gray: { inner: 'bg-gray-400', outer: 'bg-gray-300/40' },
};
const v = computed(() => VARIANTS[props.variant] || VARIANTS.green);
</script>

<template>
  <span class="flex items-center justify-center w-[15px] h-[15px] rounded-full shrink-0" :class="v.outer">
    <span class="w-2 h-2 rounded-full" :class="v.inner" />
  </span>
</template>
