<template>
  <div class="tw:grow tw:flex tw:flex-col tw-fastcloud-reset">
    <Header :nav="true"/>

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
import {onMounted, onUnmounted} from "vue";
import {state, useFastCloud} from "../state.ts";
import {apiFetch} from "../utils.ts";
import type {ApiStateResponse} from "../types";

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
