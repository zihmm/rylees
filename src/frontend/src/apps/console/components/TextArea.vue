<script setup>
import FormField from './FormField.vue';

defineProps({
  label: { type: String, default: '' },
  modelValue: { type: String, default: '' },
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
      <textarea
        :value="modelValue"
        :placeholder="placeholder"
        class="w-full min-h-[86px] rounded-field border px-4 py-3 text-[14px] text-black outline-none focus:border-accent resize-y"
        :class="hasError ? 'border-danger' : 'border-field-border'"
        @input="$emit('update:modelValue', $event.target.value)"
      />
    </template>
  </FormField>
</template>
