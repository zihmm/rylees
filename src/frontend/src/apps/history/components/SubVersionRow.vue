<script setup>
import AppIcon from '../../../shared/icons/AppIcon.vue';
import { absolute } from '../../../shared/date.js';

defineProps({
  item: { type: Object, required: true },
  language: { type: String, default: 'de' },
  isLast: { type: Boolean, default: false },
});
</script>

<template>
  <li class="relative flex items-start gap-3 pl-6 py-2">
    <!-- Vertical connector: full height for normal rows; stops at the elbow on
         the last row so it forms an "L" with no line continuing below. -->
    <span
      class="absolute left-0 top-0 w-px bg-card-border"
      :class="isLast ? 'h-[18px]' : 'bottom-0'"
      aria-hidden="true"
    ></span>
    <!-- Horizontal connector stub (elbow) -->
    <span
      class="absolute left-0 top-[18px] w-4 h-px bg-card-border"
      aria-hidden="true"
    ></span>

    <span class="shrink-0 text-meta mt-[2px]">
      <AppIcon name="document" :size="18" />
    </span>

    <div class="min-w-0">
      <p class="text-[15px] leading-[22px] font-semibold text-subhead">
        Version {{ item.version }}
      </p>
      <p class="text-[13px] leading-[22px] text-meta">
        Last Update: {{ absolute(item.publishedAt, language) }}
      </p>
    </div>
  </li>
</template>
