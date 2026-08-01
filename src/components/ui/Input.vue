<template>

  <div>
    <div v-if="label" class="tw:mb-1 tw:flex tw:items-center tw:justify-between">
      <label class="tw:font-medium tw:block" :for="name">{{ label }}</label>
      <p v-if="required" class="tw:m-0 tw:text-xs tw:text-neutral-400">{{ __('Required', 'fastcloud-offload-media') }}</p>
    </div>
    <input
        v-model="model"
        :type="type"
        :name="name"
        :placeholder="placeholder"
        :readonly="readonly"
        :id="name"
        :class="cn(
    'tw:px-3 tw:m-0 tw:py-1 tw:rounded-md tw:w-full tw:border tw:border-neutral-300 tw:placeholder:text-neutral-400 tw:outline-0 tw:shadow-none',
    !readonly && 'tw:focus:border-primary',
    readonly && 'tw:bg-neutral-100 tw:cursor-not-allowed tw:text-neutral-400',
    error && 'tw:rounded-b-none tw:border-red-500'
  )"
    />
    <p class="tw:m-0 tw:font-medium tw:bg-red-500 tw:w-full tw:px-2 tw:py-1 tw:text-center tw:text-white tw:text-xs tw:rounded-b" v-if="error">
      {{ error }}</p>
  </div>
</template>

<script lang="ts" setup>
import {cn} from "../../utils.ts";
import {__} from '@wordpress/i18n';

const model = defineModel()

withDefaults(defineProps<{
  label?: string;
  name?: string;
  type?: string;
  error?: string | null;
  placeholder?: string;
  required?: boolean;
  readonly?: boolean;
}>(), {
  type: 'text',
  required: false,
  error: null,
  name: '',
  readonly: false
})
</script>
