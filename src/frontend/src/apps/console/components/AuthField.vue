<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  modelValue: { type: String, default: '' },
  type: { type: String, default: 'text' },
  error: { type: [String, Array], default: '' },
  last: { type: Boolean, default: false },
  autocomplete: { type: String, default: undefined },
});
const emit = defineEmits(['update:modelValue']);

const focused = ref(false);

// API errors arrive as arrays (e.g. ["The username field is required."]); show the first message.
const errorText = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error) || '');

// The label floats up while the field is focused or already holds a value.
const floated = computed(() => focused.value || !!props.modelValue);
</script>

<template>
  <div>
    <label
      class="relative block"
      :class="last ? '' : (errorText ? 'border-b border-danger' : 'border-b border-field-border')"
    >
      <span
        class="absolute left-0 text-subhead pointer-events-none transition-all duration-200 ease-out"
        :class="floated ? 'top-[10px] text-[11px]' : 'top-6 text-[15px]'"
      >{{ label }}</span>
      <input
        :type="type"
        :value="modelValue"
        :autocomplete="autocomplete"
        class="w-full pt-6 pb-6 text-[15px] text-ink bg-transparent outline-none"
        @focus="focused = true"
        @blur="focused = false"
        @input="emit('update:modelValue', $event.target.value)"
      />
    </label>
    <p v-if="errorText" class="text-danger text-[11px] pb-1">{{ errorText }}</p>
  </div>
</template>
