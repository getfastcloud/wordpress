<template>
  <span :class="cn(pulseVariants(), props.class)">
    <span :class="pulsePingVariants({ variant: props.variant })"></span>
    <span :class="pulseDotVariants({ variant: props.variant })"></span>
  </span>
</template>

<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '../../utils.ts';

const pulseVariants = cva('tw:relative tw:flex tw:size-2')

const pulsePingVariants = cva(
    'tw:absolute tw:inline-flex tw:h-full tw:w-full tw:rounded-full',
    {
      variants: {
        variant: {
          default: 'tw:animate-ping tw:bg-green-400 tw:opacity-20',
          disabled: 'tw:bg-neutral-400 tw:opacity-40 tw:scale-175',
          warning: 'tw:animate-ping tw:bg-yellow-400 tw:opacity-20',
        },
      },
      defaultVariants: {
        variant: 'default',
      },
    }
)

const pulseDotVariants = cva(
    'tw:relative tw:inline-flex tw:size-2 tw:rounded-full',
    {
      variants: {
        variant: {
          default: 'tw:bg-green-500',
          disabled: 'tw:bg-neutral-500',
          warning: 'tw:bg-yellow-500',
        },
      },
      defaultVariants: {
        variant: 'default',
      },
    }
)

interface Props {
  class?: string
  variant?: VariantProps<typeof pulsePingVariants>['variant']
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
})
</script>
