<template>
  <div :class="cn(
    'tw:flex tw:gap-3 tw:items-start tw:px-4 tw:py-3 tw:rounded-md tw:w-full tw:border-l-4',
    variant === 'info'    && 'tw:bg-blue-50 tw:border-blue-400',
    variant === 'warning' && 'tw:bg-amber-50 tw:border-amber-400',
    variant === 'error'   && 'tw:bg-red-50 tw:border-red-400',
  )">

    <!-- info icon -->
    <svg v-if="variant === 'info'" class="tw:shrink-0 tw:mt-0.5 tw:text-blue-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <line x1="12" y1="8" x2="12" y2="12"/>
      <line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>

    <!-- warning icon -->
    <svg v-if="variant === 'warning'" class="tw:shrink-0 tw:mt-0.5 tw:text-amber-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
      <line x1="12" y1="9" x2="12" y2="13"/>
      <line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>

    <!-- error icon -->
    <svg v-if="variant === 'error'" class="tw:shrink-0 tw:mt-0.5 tw:text-red-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <line x1="15" y1="9" x2="9" y2="15"/>
      <line x1="9" y1="9" x2="15" y2="15"/>
    </svg>

    <p :class="cn(
      'tw:m-0 tw:text-sm',
      variant === 'info'    && 'tw:text-neutral-700',
      variant === 'warning' && 'tw:text-amber-800',
      variant === 'error'   && 'tw:text-red-800',
    )">
      <slot/>
    </p>
  </div>
</template>

<script lang="ts" setup>
import {cn} from '../utils.ts';

withDefaults(defineProps<{
  variant?: 'info' | 'warning' | 'error'
}>(), {
  variant: 'info',
})
</script>
