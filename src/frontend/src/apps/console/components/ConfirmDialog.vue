<script setup>
import AppButton from './AppButton.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

defineProps({
  title: { type: String, default: 'Are you sure?' },
  confirmLabel: { type: String, default: 'Delete' },
  cancelLabel: { type: String, default: 'Cancel' },
  loading: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
    @click.self="emit('cancel')"
  >
    <div class="w-full max-w-md rounded-card bg-white p-6 shadow-card">
      <div class="flex items-start gap-3">
        <span class="mt-0.5 shrink-0 text-danger">
          <AppIcon name="shield-exclamation" :size="22" />
        </span>
        <div class="min-w-0">
          <h3 class="text-[16px] font-semibold text-black">{{ title }}</h3>
          <div class="mt-2 text-[14px] text-subhead">
            <slot />
          </div>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-3">
        <AppButton variant="secondary" :disabled="loading" @click="emit('cancel')">{{ cancelLabel }}</AppButton>
        <AppButton variant="danger" :loading="loading" @click="emit('confirm')">{{ confirmLabel }}</AppButton>
      </div>
    </div>
  </div>
</template>
