<template>
  <div>
    <div
        class="tw:bg-yellow-200 tw:rounded-md tw:font-medium tw:mb-4 tw:text-yellow-800 tw:px-6 tw:py-2 tw:border tw:border-yellow-300 tw:shadow"
        v-if="fastcloudwp.statistics.quota_exceeded">
      {{ sprintf(__('%d files couldn\'t be offloaded — not enough storage remaining.', 'fastcloud-offload-media'), fastcloudwp.statistics.quota_exceeded) }}
    </div>
    <div
        class="tw:rounded-md tw:border tw:border-neutral-200 tw:shadow tw:justify-between tw:flex tw:items-center tw:bg-white tw:px-6 tw:py-4">
      <div class="tw:flex tw:order-2 tw:items-center tw:gap-4">
        <div class="tw:flex tw:order-3 tw:items-center tw:gap-2">
          <Pulse :variant="fastcloudwp.state.settings.enabled ? 'default' : 'disabled'"/>
          <span
              :class="fastcloudwp.state.settings.enabled ? 'tw:font-semibold tw:text-green-600' : 'tw:font-semibold tw:text-neutral-500'">
            {{
              fastcloudwp.state.settings.enabled ? __('Connected', 'fastcloud-offload-media') : __('Disconnected', 'fastcloud-offload-media')
            }}
          </span>
        </div>
        <div class="tw:space-x-2 tw:flex tw:items-center tw:text-neutral-400">
          <p>{{ fastcloudwp.state.custom_domain || fastcloudwp.state.cdn }}</p>
          <p>&mdash;</p>
          <p>{{ sprintf(__('Site ID: %s', 'fastcloud-offload-media'), fastcloudwp.state.short_id ?? '') }}</p>
        </div>
      </div>
      <div class="tw:flex tw:order-1 tw:gap-4 tw:items-center">
        <Switch
            v-model="fastcloudwp.state.settings.autosync"
            @update:modelValue="updateSettings"
            :class="fastcloudwp.state.settings.autosync ? 'tw:bg-blue-500' : 'tw:bg-gray-400'"
            class="tw:relative tw:inline-flex tw:h-6 tw:w-11 tw:items-center tw:rounded-full"
        >
          <span class="tw:sr-only">{{ __('Enable auto offload', 'fastcloud-offload-media') }}</span>
          <span
              :class="fastcloudwp.state.settings.autosync ? 'tw:translate-x-6' : 'tw:translate-x-1'"
              class="tw:inline-block tw:h-4 tw:w-4 tw:transform tw:rounded-full tw:bg-white tw:transition"
          />
        </Switch>
        <p class="tw:font-medium">
          {{ __('Auto-offload to FastCloudWP', 'fastcloud-offload-media') }}
        </p>
      </div>
    </div>

    <div class="tw:grid tw:gap-6 tw:mt-6 tw:grid-cols-3 tw:mb-4">
      <Panel :title="__('Total media', 'fastcloud-offload-media')">
        <Metric :count="fastcloudwp.statistics.total" :title="__('Total Media files in the library', 'fastcloud-offload-media')">
          <template #icon>
            <svg width="32" height="32" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                  d="M17 27L22 33L29 24L38 36H10M42 38V10C42 8.93913 41.5786 7.92172 40.8284 7.17157C40.0783 6.42143 39.0609 6 38 6H10C8.93913 6 7.92172 6.42143 7.17157 7.17157C6.42143 7.92172 6 8.93913 6 10V38C6 39.0609 6.42143 40.0783 7.17157 40.8284C7.92172 41.5786 8.93913 42 10 42H38C39.0609 42 40.0783 41.5786 40.8284 40.8284C41.5786 40.0783 42 39.0609 42 38Z"
                  fill="#1B65D8"/>
            </svg>
          </template>
        </Metric>
        <div class="tw:mt-4">
          <span v-if="fastcloudwp.state.settings.autosync"
                class="tw:inline-flex tw:items-center tw:rounded-full tw:bg-green-100 tw:px-2 tw:py-1 tw:text-xs tw:font-medium tw:text-green-700">
            {{ __('Plugin is running', 'fastcloud-offload-media') }}
          </span>
          <span v-else
                class="tw:inline-flex tw:items-center tw:rounded-full tw:bg-gray-100 tw:px-2 tw:py-1 tw:text-xs tw:font-medium tw:text-gray-600">
            {{ __('Auto-offloading is off. Upload and free your local copies manually.', 'fastcloud-offload-media') }}
          </span>
        </div>
      </Panel>

      <Offload/>
      <FreeSpace/>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Pulse from "../components/ui/Pulse.vue";
import {useFastCloud} from "../state.ts";
import {apiFetch} from "../utils.ts";
import type {ApiSettingsResponse} from "../types";
import {Switch} from "@headlessui/vue";
import Panel from "../components/Panel.vue";
import {__, sprintf} from '@wordpress/i18n';
import Metric from "../components/ui/Metric.vue";
import FreeSpace from "../components/dashboard/FreeSpace.vue";
import Offload from "../components/dashboard/Offload.vue";

const fastcloudwp = useFastCloud();
const updateSettings = async () => {
  fastcloudwp.value.isSaving = true;

  try {
    const {data} = await apiFetch<ApiSettingsResponse>('/wp-json/fastcloudwp/v1/settings', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(fastcloudwp.value.state.settings),
    });

    Object.assign(fastcloudwp.value.state.settings, data.settings);

  } finally {
    fastcloudwp.value.isSaving = false;
  }
}
</script>

<style>
p {
  margin: 0;
}
</style>
