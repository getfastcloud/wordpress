<template>
  <section v-if="fastcloudwp.mwio?.wanted"
           class="tw:rounded-md tw:border tw:border-[#bfe9cc] tw:border-t-4 tw:border-t-[#65d27e] tw:bg-[#e6f9ee] tw:px-6 tw:py-5"
           aria-label="My Website is Online">
    <p class="tw:text-xs tw:text-[#3a4149] tw:mb-2">{{ __('Also from us', 'fastcloud-offload-media') }}</p>
    <img src="../img/mwio-logo.svg" alt="My Website is Online" width="152" height="24" class="tw:block tw:h-auto tw:mb-3"/>
    <p class="tw:text-sm tw:font-semibold tw:text-[#1c1f22] tw:mb-1.5">
      {{ __('Know the moment your site goes down. Free for one site.', 'fastcloud-offload-media') }}
    </p>
    <p class="tw:text-[13px] tw:leading-relaxed tw:text-[#3a4149] tw:mb-3.5 tw:max-w-3xl">
      {{ __('My Website is Online checks your site from outside every 5 minutes and emails you when it goes down. It also warns you before your SSL certificate or your domain expires. No card needed.', 'fastcloud-offload-media') }}
    </p>
    <p class="tw:flex tw:flex-wrap tw:items-center tw:gap-x-3.5 tw:gap-y-2">
      <a v-if="fastcloudwp.mwio.action === 'install'" :href="fastcloudwp.mwio.url"
         class="thickbox open-plugin-details-modal tw:inline-flex tw:items-center tw:rounded-md tw:bg-[#12824a] tw:px-3 tw:py-2 tw:text-sm tw:font-medium tw:text-white tw:no-underline tw:hover:bg-[#0e6a3c] tw:hover:text-white tw:focus:text-white">
        {{ __('Install My Website is Online', 'fastcloud-offload-media') }}
      </a>
      <a v-else-if="fastcloudwp.mwio.action === 'activate'" :href="fastcloudwp.mwio.url"
         class="tw:inline-flex tw:items-center tw:rounded-md tw:bg-[#12824a] tw:px-3 tw:py-2 tw:text-sm tw:font-medium tw:text-white tw:no-underline tw:hover:bg-[#0e6a3c] tw:hover:text-white tw:focus:text-white">
        {{ __('Activate My Website is Online', 'fastcloud-offload-media') }}
      </a>
      <a href="https://mywebsiteisonline.com/" target="_blank" rel="noopener"
         class="tw:text-sm tw:font-medium tw:text-[#12824a] tw:hover:text-[#0e6a3c]">
        {{ __('Learn more', 'fastcloud-offload-media') }}
      </a>
    </p>
  </section>
</template>

<script lang="ts" setup>
import {useFastCloud} from "../state.ts";
import {__} from '@wordpress/i18n';

const fastcloudwp = useFastCloud();
</script>
