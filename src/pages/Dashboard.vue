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
          <Pulse :variant="pulseVariant"/>
          <span class="tw:font-semibold" :class="statusClass">{{ statusLabel }}</span>
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

    <div class="tw:grid tw:gap-6 tw:mt-6 tw:grid-cols-3 tw:mb-6">
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

    <Panel :title="__('Support', 'fastcloud-offload-media')">
      <div class="tw:grid tw:grid-cols-3 tw:gap-6">
        <div class="tw:flex tw:flex-col tw:gap-3">
          <div class="tw:flex tw:items-center tw:gap-2 tw:font-semibold tw:text-neutral-800">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tw:text-primary"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            {{ __('WordPress.org Forum', 'fastcloud-offload-media') }}
          </div>
          <p class="tw:text-sm tw:text-neutral-500">{{ __('Post a question on the official WordPress.org support forum.', 'fastcloud-offload-media') }}</p>
          <Button as="a" href="https://wordpress.org/support/plugin/fastcloud-offload-media/" target="_blank" variant="secondary" class="tw:self-start tw:mt-auto">
            {{ __('Open Forum', 'fastcloud-offload-media') }}
          </Button>
        </div>

        <div class="tw:flex tw:flex-col tw:gap-3">
          <div class="tw:flex tw:items-center tw:gap-2 tw:font-semibold tw:text-neutral-800">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tw:text-primary"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            {{ __('Email Support', 'fastcloud-offload-media') }}
          </div>
          <p class="tw:text-sm tw:text-neutral-500">{{ __('Send us an email and we\'ll get back to you as soon as possible.', 'fastcloud-offload-media') }}</p>
          <Button as="a" href="mailto:support@fastcloudwp.com" variant="secondary" class="tw:self-start tw:mt-auto">
            {{ __('Send Email', 'fastcloud-offload-media') }}
          </Button>
        </div>

        <div class="tw:flex tw:flex-col tw:gap-3">
          <div class="tw:flex tw:items-center tw:gap-2 tw:font-semibold tw:text-neutral-800">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="color: #5865F2"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.03.056a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
            {{ __('Discord Community', 'fastcloud-offload-media') }}
          </div>
          <p class="tw:text-sm tw:text-neutral-500">{{ __('Join the community, ask questions, and get help from other users.', 'fastcloud-offload-media') }}</p>
          <Button as="a" href="https://discord.com/invite/3abp9jr34" target="_blank" variant="secondary" class="tw:self-start tw:mt-auto">
            {{ __('Join Discord', 'fastcloud-offload-media') }}
          </Button>
        </div>
      </div>
    </Panel>
  </div>
</template>

<script lang="ts" setup>
import Pulse from "../components/ui/Pulse.vue";
import {useFastCloud} from "../state.ts";
import {apiFetch} from "../utils.ts";
import type {ApiSettingsResponse} from "../types";
import {Switch} from "@headlessui/vue";
import {computed} from 'vue';
import Panel from "../components/Panel.vue";
import Button from "../components/ui/Button.vue";
import {__, sprintf} from '@wordpress/i18n';
import Metric from "../components/ui/Metric.vue";
import FreeSpace from "../components/dashboard/FreeSpace.vue";
import Offload from "../components/dashboard/Offload.vue";

const fastcloudwp = useFastCloud();

const cdnPending = computed(() => fastcloudwp.value.state.connected && !fastcloudwp.value.state.cdn_ready);

const pulseVariant = computed(() => {
  if (!fastcloudwp.value.state.settings.enabled) return 'disabled' as const;
  if (cdnPending.value) return 'warning' as const;
  return 'default' as const;
});

const statusLabel = computed(() => {
  if (!fastcloudwp.value.state.settings.enabled) return __('Disconnected', 'fastcloud-offload-media');
  if (cdnPending.value) return __('Configuring CDN…', 'fastcloud-offload-media');
  return __('Connected', 'fastcloud-offload-media');
});

const statusClass = computed(() => {
  if (!fastcloudwp.value.state.settings.enabled) return 'tw:text-neutral-500';
  if (cdnPending.value) return 'tw:text-yellow-600';
  return 'tw:text-green-600';
});
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
