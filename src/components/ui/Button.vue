<template>
  <button
      :type="type"
      :disabled="loading"
      :class="cn(buttonVariants({ variant, size }), loading ? 'tw:opacity-75 tw:cursor-not-allowed' : '', props.class)"
  >
    <svg v-if="loading" class="tw:mr-2 tw:size-4 tw:animate-spin tw:shrink-0" viewBox="0 0 24 24" fill="none">
      <circle class="tw:opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="tw:opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
    </svg>
    <slot />
  </button>
</template>

<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority'
import { toRefs } from 'vue'
import { cn } from '../../utils.ts';

const buttonVariants = cva(
    'tw:inline-flex tw:items-center tw:justify-center tw:rounded-md tw:font-medium tw:text-center tw:max-w-full',
    {
      variants: {
        variant: {
          default: 'tw:bg-primary tw:text-white tw:border tw:border-primary',
          secondary: 'tw:bg-neutral-100 tw:text-neutral-900',
          danger: 'tw:text-red-700 tw:border tw:hover:bg-red-700 tw:hover:text-white tw:transition-all tw:transition-250 tw:border-red-700',
          outline: 'tw:border tw:border-primary tw:bg-primary/10 tw:text-primary',
        },
        size: {
          default: 'tw:px-3 tw:py-2',
          sm: 'tw:px-3 tw:py-2 tw:text-sm',
          lg: 'tw:px-5 tw:py-3.5',
        },
      },
      defaultVariants: {
        variant: 'default',
        size: 'default',
      },
    }
)

type ButtonVariantProps = VariantProps<typeof buttonVariants>

interface Props {
  type?: 'button' | 'submit' | 'reset'
  class?: string
  variant?: ButtonVariantProps['variant']
  size?: ButtonVariantProps['size']
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'button',
  variant: 'default',
  size: 'default',
  loading: false,
})

const { type, variant, size, loading } = toRefs(props)
</script>
