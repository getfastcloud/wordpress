<template>
  <div :class="cn(
    'tw:flex tw:gap-3 tw:items-start tw:px-4 tw:py-3 tw:rounded-md tw:w-full',
    variant === 'default' && 'tw:bg-yellow-100',
    variant === 'info'    && 'tw:bg-blue-50 tw:border-l-4 tw:border-blue-400',
  )">
    <!-- info icon -->
    <svg v-if="variant === 'info'" class="tw:shrink-0 tw:mt-0.5 tw:text-blue-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <line x1="12" y1="8" x2="12" y2="12"/>
      <line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <p :class="cn('tw:m-0 tw:text-sm', variant === 'default' && 'tw:text-yellow-800', variant === 'info' && 'tw:text-neutral-700')">
      <slot/>
    </p>
  </div>
</template>

<script lang="ts" setup>
import {cn} from '../utils.ts';

withDefaults(defineProps<{
  variant?: 'default' | 'info'
}>(), {
  variant: 'default',
})
</script>
