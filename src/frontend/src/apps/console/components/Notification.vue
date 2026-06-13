<script setup>
import { computed, onMounted, watch } from 'vue';
import AppIcon from '../../../shared/icons/AppIcon.vue';

const props = defineProps({
  type: { type: String, default: 'error' }, // 'error' | 'warning' | 'success'
  title: { type: String, default: '' },
  message: { type: String, default: '' },
});

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Surface the notification by bringing the top of the page into view whenever it appears or changes.
onMounted(scrollToTop);
watch(() => [props.type, props.title, props.message], scrollToTop);

// Exact tokens from the Figma "Notifications" frame.
const VARIANTS = {
  error: {
    icon: 'cross-circle', defaultTitle: 'Error',
    bg: 'bg-[#fceceb]', border: 'border-[#fcd5dc]', title: 'text-[#a43a41]',
  },
  warning: {
    icon: 'shield-exclamation', defaultTitle: 'Warning',
    bg: 'bg-[#fefbed]', border: 'border-[#f1f0d7]', title: 'text-[#d5ad57]',
  },
  success: {
    icon: 'check-circle', defaultTitle: 'Success',
    bg: 'bg-[#e5f9ee]', border: 'border-[#d9f2e6]', title: 'text-[#4d9365]',
  },
};

const v = computed(() => VARIANTS[props.type] || VARIANTS.error);
</script>

<template>
  <div class="flex items-center gap-[15px] rounded-[5px] border px-[15px] py-4" :class="[v.bg, v.border]">
    <AppIcon :name="v.icon" :size="25" class="shrink-0" :class="v.title" />
    <div class="min-w-0">
      <p class="text-[14px] font-medium leading-none" :class="v.title">{{ title || v.defaultTitle }}</p>
      <p class="text-[14px] text-black leading-[18px] mt-1.5">{{ message }}</p>
    </div>
  </div>
</template>
