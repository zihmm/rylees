<script setup>
import { ref } from 'vue';
import FormField from './FormField.vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

const props = defineProps({
  label: { type: String, default: 'Token' },
  token: { type: String, default: '' },
  helper: { type: String, default: 'Use this token in your CLI project' },
});

const copied = ref(false);
function copy() {
  navigator.clipboard?.writeText(props.token);
  copied.value = true;
  setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
  <FormField :label="label" :helper="helper">
    <div class="flex items-start gap-2 min-h-11 rounded-field bg-[rgba(233,233,234,0.42)] px-4 py-3">
      <code class="flex-1 font-mono text-[14px] text-black break-all leading-relaxed">{{ token }}</code>
      <button
        type="button"
        class="text-helper hover:text-black shrink-0 mt-0.5"
        :aria-label="copied ? 'Copied' : 'Copy to clipboard'"
        @click="copy"
      >
        <AppIcon name="copy" :size="16" />
      </button>
    </div>
  </FormField>
</template>
