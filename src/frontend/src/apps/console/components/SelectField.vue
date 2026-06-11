<script setup>
import FormField from './FormField.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

defineProps({
  label: { type: String, default: '' },
  modelValue: { type: [String, Number, null], default: '' },
  options: { type: Array, default: () => [] }, // [{ value, label }]
  helper: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
});
defineEmits(['update:modelValue']);
</script>

<template>
  <FormField :label="label" :helper="helper" :error="error" :required="required">
    <template #default="{ hasError }">
      <div class="relative">
        <select
          :value="modelValue"
          class="w-full h-11 rounded-field border pl-4 pr-10 text-[14px] text-black bg-white appearance-none outline-none focus:border-accent"
          :class="hasError ? 'border-danger' : 'border-field-border'"
          @change="$emit('update:modelValue', $event.target.value)"
        >
          <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
          <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <AppIcon name="chevron-down" :size="16" class="absolute right-3 top-1/2 -translate-y-1/2 text-helper pointer-events-none" />
      </div>
    </template>
  </FormField>
</template>
