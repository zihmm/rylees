<script setup>
import AppIcon from '../../../shared/icons/AppIcon.vue';

defineProps({
  variant: { type: String, default: 'primary' }, // 'primary' | 'secondary'
  icon: { type: String, default: '' },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex items-center justify-center gap-2 h-9 px-5 rounded-field text-[13px] font-medium text-white transition-opacity disabled:opacity-60"
    :class="variant === 'secondary' ? 'bg-field-border' : 'bg-accent'"
  >
    <AppIcon v-if="icon" :name="icon" :size="14" />
    <slot />
  </button>
</template>
