<template>
  <div class="tw:flex tw:gap-4 tw:py-4">
    <div class="tw:min-w-[130px] tw:text-neutral-400 tw:shrink-0">{{ diffHuman }}</div>
    <div class="tw:flex tw:flex-col tw:gap-1">
      <div>
        <template v-for="(part, i) in messageParts" :key="i">
          <span
              v-if="part.type === 'placeholder'"
              class="tw:rounded tw:bg-neutral-100 tw:px-1 tw:font-medium tw:text-xs tw:py-1 tw:font-mono tw:text-sm tw:text-neutral-600"
          >{{ part.value }}</span>
          <template v-else>{{ part.value }}</template>
        </template>
      </div>
      <div v-if="showContext && log.context" class="tw:flex tw:flex-wrap tw:gap-1">
        <span
            v-for="(value, key) in log.context"
            :key="key"
            class="tw:inline-flex tw:gap-1 tw:rounded tw:bg-blue-950 tw:px-1.5 tw:py-0.5 tw:font-mono tw:text-xs tw:text-blue-200"
        >
          <span class="tw:text-blue-400">{{ key }}</span>
          <span>{{ value }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import type {Log} from "../../types";
import moment from 'moment';
import {computed} from "vue";

type Part = { type: 'text' | 'placeholder'; value: string }

const props = withDefaults(defineProps<{
  log: Log
  showContext?: boolean
}>(), {
  showContext: false
});

const diffHuman = computed(() => {
  return moment.utc(props.log.created_at).local().fromNow()
})

const messageParts = computed((): Part[] => {
  const parts: Part[] = []
  const context = props.log.context ?? {}
  const regex = /\{(\w+)\}/g

  let lastIndex = 0
  let match: RegExpExecArray | null

  while ((match = regex.exec(props.log.message)) !== null) {
    if (match.index > lastIndex) {
      parts.push({type: 'text', value: props.log.message.slice(lastIndex, match.index)})
    }

    const key = match[1]
    if (!key) {
      continue
    }
    parts.push({
      type: 'placeholder',
      // Fall back to the raw {key} if not found in context
      value: key in context ? String(context[key]) : match[0],
    })

    lastIndex = regex.lastIndex
  }

  if (lastIndex < props.log.message.length) {
    parts.push({type: 'text', value: props.log.message.slice(lastIndex)})
  }

  return parts
})
</script>
