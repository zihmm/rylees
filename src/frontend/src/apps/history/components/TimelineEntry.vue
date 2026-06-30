<script setup>
import { relative } from '../../../shared/date.js';
import MarkdownBody from '../../../shared/MarkdownBody.vue';

defineProps({
  item: { type: Object, required: true },
  isActive: { type: Boolean, default: false },
  translating: { type: Boolean, default: false },
  language: { type: String, default: 'de' },
});
</script>

<template>
  <li class="relative pl-[70px] pt-[5px] pb-[72px] last:pb-0">
    <!-- Marker. The bubble carries a bg-card halo (inset -10px) that masks the
         timeline line, leaving a 10px gap. A single bubble morphs between the
         active state (21px outlined ring) and the resting state (13px filled
         dot) so it animates smoothly as the active entry changes on scroll. -->
    <span class="absolute left-0 top-1 flex w-[21px] h-[21px] items-center justify-center" aria-hidden="true">
      <span class="absolute inset-[-10px] rounded-full bg-card"></span>
      <span
        class="relative rounded-full border-accent transition-all duration-300 ease-out"
        :class="isActive ? 'w-[21px] h-[21px] border-[3px] bg-card' : 'w-[13px] h-[13px] border-0 bg-accent'"
      ></span>
    </span>

    <!-- Version pill -->
    <div class="mb-[30px]">
      <span class="inline-block bg-pill-bg text-pill-text font-bold text-[12px] rounded-pill px-3 py-1">
        v{{ item.version }}
      </span>
    </div>

    <!-- Body or skeleton -->
    <template v-if="translating">
      <div class="animate-pulse bg-gray-200 rounded h-4 w-full mb-2"></div>
      <div class="animate-pulse bg-gray-200 rounded h-4 w-3/4"></div>
    </template>
    <template v-else>
      <MarkdownBody :html="item.body" class="text-[14px] leading-[22px] text-black" />
    </template>

    <!-- Meta date -->
    <p class="mt-[30px] text-[13px] leading-[22px] text-meta">
      {{ relative(item.publishedAt, language) }}
    </p>
  </li>
</template>
