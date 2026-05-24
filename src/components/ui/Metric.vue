<template>
  <div class="tw:flex tw:gap-4">
    <div class="tw:w-14 tw:relative tw:h-14 tw:rounded-full tw:bg-light-primary">
      <div class="tw:absolute tw:left-1/2 tw:top-1/2 tw:-translate-x-1/2 tw:h-[32px] tw:w-[32px] tw:-translate-y-1/2">
        <slot name="icon"/>
      </div>
    </div>
    <div>
      <p class="tw:font-extrabold tw:text-4xl tw:flex tw:items-end tw:gap-2">{{ count }}</p>
      <p class="tw:text-[14px] tw:text-neutral-500">{{ title }}</p>
      <p v-if="meta" class="tw:text-xs tw:text-neutral-500">
        {{ meta }}
      </p>
    </div>
  </div>
</template>

<script lang="ts" setup>
defineProps<{
  count: number
  title: string
  meta?: string;
}>()
</script>
