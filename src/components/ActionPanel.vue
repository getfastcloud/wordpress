<template>
  <div class="tw:bg-white tw:px-6 tw:py-5 tw:relative tw:rounded-md tw:border tw:border-neutral-200 tw:shadow">
    <div class="tw:flex tw:gap-4">
      <div class="tw:w-16 tw:relative tw:h-16 tw:rounded-full tw:bg-light-primary">
        <div class="tw:absolute tw:left-1/2 tw:top-1/2 tw:-translate-x-1/2 tw:h-[38px] tw:w-[38px] tw:-translate-y-1/2">
          <slot name="icon"/>
        </div>
      </div>
      <div>
        <p class="tw:font-semibold tw:text-[16px]">{{ title }}</p>
        <p class="tw:text-neutral-400">{{ subtitle }}</p>
      </div>
    </div>
    <div class="tw:flex tw:items-center tw:justify-end">
      <slot/>
    </div>
  </div>
</template>

<script lang="ts" setup>
defineProps<{
  title: string;
  subtitle: string
}>();
</script>
