<script setup>
defineProps({
  label: { type: String, default: '' },
  helper: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
});
</script>

<template>
  <div class="flex flex-col sm:flex-row gap-2 sm:gap-8 py-6 border-b border-field-border">
    <div class="sm:w-48 shrink-0 sm:pt-3">
      <label class="text-[13px]" :class="error ? 'text-danger' : 'text-field-label'">
        {{ label }}<span v-if="required" aria-hidden="true"> *</span>
      </label>
      <p v-if="helper" class="text-helper text-[11px] mt-1 leading-tight">{{ helper }}</p>
    </div>
    <div class="flex-1 max-w-md">
      <slot :hasError="!!error" />
      <p v-if="error" class="text-danger text-[11px] mt-1">{{ error }}</p>
    </div>
  </div>
</template>
