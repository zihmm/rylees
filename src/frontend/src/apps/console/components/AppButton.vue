<script setup>
import AppIcon from '../../../shared/icons/AppIcon.vue';

defineProps({
  variant: { type: String, default: 'primary' }, // 'primary' | 'secondary'
  size: { type: String, default: 'md' }, // 'md' | 'lg'
  icon: { type: String, default: '' },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="relative inline-flex items-center justify-center gap-2 rounded-field font-medium text-white transition-colors disabled:bg-field-border disabled:cursor-default"
    :class="[
      variant === 'secondary' ? 'bg-field-border' : 'bg-accent',
      size === 'lg' ? 'h-12 px-10 text-[15px]' : 'h-9 px-5 text-[13px]',
    ]"
  >
    <!-- Spinner is absolutely centered so the button keeps its dimensions while loading. -->
    <span v-if="loading" class="absolute inset-0 flex items-center justify-center">
      <span
        class="rounded-full border-2 border-muted/40 border-t-subhead animate-spin"
        :class="size === 'lg' ? 'h-5 w-5' : 'h-4 w-4'"
      />
    </span>
    <span class="inline-flex items-center gap-2" :class="{ invisible: loading }">
      <AppIcon v-if="icon" :name="icon" :size="14" />
      <slot />
    </span>
  </button>
</template>
