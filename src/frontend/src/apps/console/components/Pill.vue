<script setup>
defineProps({
  label: { type: String, required: true },
  variant: { type: String, default: 'version' }, // 'version' | 'beta'
});
</script>

<template>
  <span
    class="inline-flex items-center rounded-pill px-3 py-1 text-[11px] font-extrabold text-pill-text leading-none"
    :class="variant === 'beta' ? 'bg-beta-bg' : 'bg-pill-bg'"
  >
    {{ label }}
  </span>
</template>
