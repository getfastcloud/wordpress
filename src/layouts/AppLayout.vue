<template>
  <div class="tw:grow tw:flex tw:flex-col tw-fastcloud-reset">
    <Header :nav="true"/>

    <div v-if="fastcloudwp.state.account_status === 'pending_register'" class="tw:px-6 tw:pt-4">
      <Notice variant="warning">{{ __('Check your email and click the link we sent to create your account. Your account locks after', 'fastcloud-offload-media') }} <strong>{{ __('7 days', 'fastcloud-offload-media') }}</strong> {{ __('if not confirmed.', 'fastcloud-offload-media') }} <RouterLink to="/settings" class="tw:underline tw:font-medium tw:text-amber-900 hover:tw:text-amber-700">{{ __('Email not received?', 'fastcloud-offload-media') }}</RouterLink></Notice>
    </div>

    <div v-if="fastcloudwp.state.account_status === 'pending_approve'" class="tw:px-6 tw:pt-4">
      <Notice variant="warning">{{ __('Check your email and click the link to approve adding this site to your account.', 'fastcloud-offload-media') }} <RouterLink to="/settings" class="tw:underline tw:font-medium tw:text-amber-900 hover:tw:text-amber-700">{{ __("Didn't receive the email?", 'fastcloud-offload-media') }}</RouterLink></Notice>
    </div>

    <div class="tw:px-6 tw:pb-8">
      <div class="wrap tw:m-0 tw:py-6">
        <main class="tw:clear-left tw:max-w-[1440px]">
          <slot/>
        </main>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Header from "../components/Header.vue";
import Notice from "../components/Notice.vue";
import {onMounted, onUnmounted} from "vue";
import {RouterLink} from "vue-router";
import {state, useFastCloud} from "../state.ts";
import {apiFetch} from "../utils.ts";
import type {ApiStateResponse} from "../types";
import {__} from '@wordpress/i18n';

let interval: ReturnType<typeof setInterval> | null = null;
let currentRequest: AbortController | null = null;

const fastcloudwp = useFastCloud();

async function refreshState() {
  if (fastcloudwp.value.isSaving) return;

  currentRequest?.abort();
  currentRequest = new AbortController();

  try {
    const {response, data} = await apiFetch<ApiStateResponse>('/wp-json/fastcloudwp/v1/state', {
      signal: currentRequest.signal,
    });

    if (response.status === 403) {
      fastcloudwp.value.state.connected = false;
      return;
    }

    const {state: {state, statistics, logs}} = data;

    fastcloudwp.value.statistics = statistics ?? fastcloudwp.value.statistics;
    fastcloudwp.value.logs = logs ?? fastcloudwp.value.logs;

    const {settings, ...connectionState} = state;
    Object.assign(fastcloudwp.value.state, connectionState);
  } catch (e) {
    if ((e as Error).name !== 'AbortError') {
      console.warn('[fastcloudwp] poll failed', e);
    }
  }
}

onMounted(async () => {
  if (!state.value.state.connected) {
    return;
  }

  await refreshState();
  interval = setInterval(refreshState, 3000);
});

onUnmounted(() => {
  if (interval) {
    clearInterval(interval);
    interval = null;
  }
  currentRequest?.abort();
});
</script>
