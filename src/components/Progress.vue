<template>
  <div class="tw:flex tw:items-center tw:gap-3">
    <div class="tw:overflow-hidden tw:grow tw:rounded-full tw:bg-gray-200">
      <div
          :style="{ width: percent + '%' }"
          class="tw:h-2 tw:rounded-full tw:bg-primary tw:transition-all tw:duration-500 tw:ease-out"
      ></div>
    </div>
    <div class="tw:loading-none tw:mb-0.5 tw:text-[14px] tw:text-primary tw:font-bold">{{percent}}%</div>
  </div>
</template>

<script setup lang="ts">
import {computed} from "vue";

const props = defineProps<{
  value: number;
}>()

const percent = computed(() => Math.min(100, Math.max(0, props.value ?? 0)).toFixed(0))
</script>
