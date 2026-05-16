<template>
  <div class="tw:bg-white tw:px-5 tw:py-5 tw:rounded-md tw:border tw:shadow tw:border-neutral-200 tw:relative">
    <h2 class="tw:mt-0 tw:mx-0 tw:mb-4 tw:font-semibold">{{ title }}</h2>
    <slot/>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
}>()
</script>
