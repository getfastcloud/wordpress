<template>
  <div class="tw:relative" :style="{ width: `${props.size}px`, height: `${props.size}px` }">
    <svg class="tw:h-full tw:w-full" viewBox="0 0 100 100">
      <circle class="tw:stroke-current tw:text-gray-200" stroke-width="10" cx="50" cy="50" r="40"
              fill="transparent"></circle>
      <circle
          class="tw:stroke-current"
          :class="{'tw:text-primary': stats < 100, 'tw:text-[#13AE16]': stats >= 100}"
          style="transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 0.35s;"
          stroke-width="6"
          stroke-linecap="round"
          cx="50"
          cy="50"
          r="40"
          fill="transparent"
          stroke-dasharray="251.2"
          :stroke-dashoffset="`calc(251.2px - (251.2px * ${props.stats}) / 100)`"></circle>
      <text x="50" y="50" font-size="12" text-anchor="middle" alignment-baseline="middle">{{ formattedStats }}%</text>
    </svg>
  </div>
</template>

<script lang="ts" setup>
import {computed} from "vue";

const props = withDefaults(defineProps<{
  count: number;
  total: number;
  stats: number;
  size?: number;
}>(), {
  size: 160,
})

const formattedStats = computed(() => {
  return props.stats % 1 === 0 ? String(Math.round(props.stats)) : props.stats.toFixed(2)
})
</script>
