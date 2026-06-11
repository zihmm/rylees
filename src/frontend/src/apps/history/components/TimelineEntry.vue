<script setup>
import { relative } from '../../../shared/date.js';

defineProps({
  item: { type: Object, required: true },
  isNewest: { type: Boolean, default: false },
  translating: { type: Boolean, default: false },
  language: { type: String, default: 'de' },
});
</script>

<template>
  <li class="relative pl-10 pb-8 last:pb-0">
    <!-- Marker -->
    <span class="absolute left-0 top-1 flex items-center justify-center" aria-hidden="true">
      <span
        v-if="isNewest"
        class="block w-[21px] h-[21px] rounded-full border-2 border-accent bg-card"
      ></span>
      <span
        v-else
        class="block w-[13px] h-[13px] rounded-full bg-accent ml-1"
      ></span>
    </span>

    <!-- Version pill -->
    <div class="mb-2">
      <span class="inline-block bg-pill-bg text-pill-text font-bold text-[13px] rounded-pill px-3 py-1">
        v{{ item.version }}
      </span>
    </div>

    <!-- Body or skeleton -->
    <template v-if="translating">
      <div class="animate-pulse bg-gray-200 rounded h-4 w-full mb-2"></div>
      <div class="animate-pulse bg-gray-200 rounded h-4 w-3/4"></div>
    </template>
    <template v-else>
      <p class="text-[15px] leading-[22px] text-black whitespace-pre-line">{{ item.body }}</p>
    </template>

    <!-- Meta: relative date only -->
    <p class="mt-2 text-[13px] leading-[22px] text-meta">
      {{ relative(item.publishedAt, language) }}
    </p>
  </li>
</template>
