<template>
  <div class="tw:bg-white tw:px-5 tw:py-5 tw:rounded-md tw:mt-6 tw:border tw:shadow tw:border-neutral-200 tw:relative">
    <div class="tw:flex tw:items-center tw:gap-3 tw:mb-2">
      <div class="tw:relative tw:flex tw:justify-start">
        <span class="tw:pr-2 tw:text-sm tw:shrink-0 tw:text-gray-600 tw:uppercase tw:tracking-wide tw:font-bold">{{ __('Recent Activity', 'fastcloud-offload-media') }}</span>
      </div>
      <div aria-hidden="true" class="tw:w-full tw:border-t tw:border-gray-300"></div>
    </div>
    <div class="tw:divide-y tw:divide-neutral-200">
      <Log :log="log" :key="log.id" v-for="log in fastcloudwp.logs"/>
    </div>
  </div>
</template>

<script lang="ts" setup>
import {useFastCloud} from "../../state.ts";
import Log from "../ui/Log.vue";
import {__} from '@wordpress/i18n';

const fastcloudwp = useFastCloud();
</script>
