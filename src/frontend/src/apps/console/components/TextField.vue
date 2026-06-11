<script setup>
import FormField from './FormField.vue';

defineProps({
  label: { type: String, default: '' },
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
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
      <input
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        class="w-full h-11 rounded-field border px-4 text-[14px] text-black outline-none focus:border-accent"
        :class="hasError ? 'border-danger' : 'border-field-border'"
        @input="$emit('update:modelValue', $event.target.value)"
      />
    </template>
  </FormField>
</template>
