<template>
  <div class="tw:max-w-3xl">
    <div class="tw:border tw:bg-white tw:border-neutral-200 tw:shadow tw:rounded-md tw:mb-6 tw:p-6">
      <p class="tw:font-medium tw:text-[14px]">{{ __('Storage Usage', 'fastcloud-offload-media') }}</p>
      <p class="tw:text-neutral-400 tw:mb-3">
        {{ __('How much of your FastCloudWP storage is currently used across all your sites.', 'fastcloud-offload-media') }}
      </p>

      <div class="tw:w-full tw:bg-neutral-200 tw:rounded-full tw:h-2 tw:overflow-hidden">
        <div
            class="tw:h-2 tw:rounded-full tw:transition-all"
            :class="storageBarColor"
            :style="{ width: `${storagePercent}%` }"
        ></div>
      </div>

      <p class="tw:text-[13px] tw:text-neutral-600 tw:mt-2">
        <strong>{{ formatBytes(storage.used) }}</strong> /
        {{ formatBytes(storage.total) }}
        ({{ storagePercent.toFixed(2) }}%)
      </p>
    </div>

    <div class="tw:border tw:bg-white tw:border-neutral-200 tw:shadow tw:rounded-md tw:divide-y tw:divide-neutral-200">
      <div class="tw:px-6 tw:pt-6 tw:pb-4">
        <p class="tw:text-[16px] tw:font-semibold">{{ __('Plugin Configuration', 'fastcloud-offload-media') }}</p>
      </div>
      <div class="tw:p-6 tw:flex tw:gap-4 tw:justify-between tw:items-center">
        <div>
          <p class="tw:font-medium tw:text-[14px]">{{ __('Enable FastCloudWP', 'fastcloud-offload-media') }}</p>
          <p class="tw:text-neutral-400">{{ __('When enabled, media URLs are rewritten to serve files from FastCloudWP. When disabled, all media are served locally.', 'fastcloud-offload-media') }}</p>
        </div>
        <Switch
            v-model="fastcloudwp.state.settings.enabled"
            @update:modelValue="updateSettings"
            :class="fastcloudwp.state.settings.enabled ? 'tw:bg-blue-500' : 'tw:bg-gray-400'"
            class="tw:relative tw:inline-flex tw:h-6 tw:w-11 tw:items-center tw:rounded-full tw:shrink-0"
        >
          <span class="tw:sr-only">{{ __('Enable FastCloudWP', 'fastcloud-offload-media') }}</span>
          <span
              :class="fastcloudwp.state.settings.enabled ? 'tw:translate-x-6' : 'tw:translate-x-1'"
              class="tw:inline-block tw:h-4 tw:w-4 tw:transform tw:rounded-full tw:bg-white tw:transition"
          />
        </Switch>
      </div>
      <div
          class="tw:flex tw:flex-col tw:border-l-2 tw:ml-6 tw:transition-all"
          :class="fastcloudwp.state.settings.enabled ? 'tw:border-neutral-200' : 'tw:border-neutral-200'"
      >
        <div
            class="tw:p-6 tw:flex tw:gap-4 tw:justify-between tw:items-center tw:transition-opacity"
            :class="!fastcloudwp.state.settings.enabled ? 'tw:opacity-40 tw:cursor-not-allowed tw:pointer-events-none' : ''"
        >
          <div>
            <p class="tw:font-medium tw:text-[14px]">{{ __('Enable Automatic Offloading', 'fastcloud-offload-media') }}</p>
            <p class="tw:text-neutral-400">{{ __('Automatically upload new media files to FastCloudWP as soon as they are added to the media library.', 'fastcloud-offload-media') }}</p>
          </div>
          <Switch
              v-model="fastcloudwp.state.settings.autosync"
              @update:modelValue="updateSettings"
              :class="(fastcloudwp.state.settings.autosync && fastcloudwp.state.settings.enabled) ? 'tw:bg-blue-500' : 'tw:bg-gray-400'"
              class="tw:relative tw:inline-flex tw:h-6 tw:w-11 tw:items-center tw:rounded-full tw:shrink-0"
          >
            <span class="tw:sr-only">{{ __('Enable Automatic Offloading', 'fastcloud-offload-media') }}</span>
            <span
                :class="(fastcloudwp.state.settings.autosync && fastcloudwp.state.settings.enabled) ? 'tw:translate-x-6' : 'tw:translate-x-1'"
                class="tw:inline-block tw:h-4 tw:w-4 tw:transform tw:rounded-full tw:bg-white tw:transition"
            />
          </Switch>
        </div>
        <div
            class="tw:p-6 tw:flex tw:gap-4 tw:justify-between tw:items-center tw:border-t tw:border-neutral-200 tw:transition-opacity"
            :class="!fastcloudwp.state.settings.enabled ? 'tw:opacity-40 tw:cursor-not-allowed tw:pointer-events-none' : ''"
        >
          <div>
            <p class="tw:font-medium tw:text-[14px]">{{ __('Remove Local Copies After Offload', 'fastcloud-offload-media') }}</p>
            <p class="tw:text-neutral-400">{{ __('Free up server disk space by deleting local files once they\'ve been safely stored on FastCloudWP.', 'fastcloud-offload-media') }}</p>
          </div>
          <Switch
              v-model="fastcloudwp.state.settings.delete_media"
              @update:modelValue="updateSettings"
              :class="(fastcloudwp.state.settings.delete_media && fastcloudwp.state.settings.enabled) ? 'tw:bg-blue-500' : 'tw:bg-gray-400'"
              class="tw:relative tw:inline-flex tw:h-6 tw:w-11 tw:items-center tw:rounded-full tw:shrink-0"
          >
            <span class="tw:sr-only">{{ __('Enable local delete', 'fastcloud-offload-media') }}</span>
            <span
                :class="(fastcloudwp.state.settings.delete_media && fastcloudwp.state.settings.enabled) ? 'tw:translate-x-6' : 'tw:translate-x-1'"
                class="tw:inline-block tw:h-4 tw:w-4 tw:transform tw:rounded-full tw:bg-white tw:transition"
            />
          </Switch>
        </div>
      </div>
      <div class="tw:p-6 tw:flex tw:gap-4 tw:justify-between tw:items-center">
        <div class="tw:w-full">
          <p class="tw:font-medium tw:text-[14px]">{{ __('Site Key', 'fastcloud-offload-media') }}</p>
          <p class="tw:text-neutral-400 tw:mb-2">{{ __('Your key connects this site to FastCloudWP. Read-only.', 'fastcloud-offload-media') }}</p>
          <div class="tw:flex tw:gap-2 tw:items-center tw:w-full">
            <Input v-model="fastcloudwp.state.sitekey" class="tw:grow" :readonly="true"/>
            <Button class="tw:w-[100px]" @click.prevent="copySiteKey" variant="secondary">{{
                copied ? __('Copied', 'fastcloud-offload-media') : __('Copy', 'fastcloud-offload-media')
              }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <div
        class="tw:border-l-4 tw:border-red-700 tw:shadow tw:border-t tw:border-r tw:border-b tw:rounded-r-md tw:bg-white tw:mt-6 tw:divide-y tw:divide-neutral-200">
      <div class="tw:px-6 tw:pt-6 tw:pb-4">
        <p class="tw:text-[16px] tw:font-semibold tw:text-red-700">{{ __('Danger Zone', 'fastcloud-offload-media') }}</p>
      </div>
      <div class="tw:p-6 tw:flex tw:gap-4 tw:justify-between tw:items-center">
        <div class="tw:w-full tw:max-w-[580px]">
          <p class="tw:font-medium tw:text-[14px] tw:text-red-700">{{ __('Remove original image from server', 'fastcloud-offload-media') }}</p>
          <p class="tw:text-neutral-400 tw:mb-2">{{ __('The original full-size image will be removed from your server after being offloaded to FastCloudWP. Enabling this may break image editing, regeneration, and third-party plugins such as Imagify or ShortPixel.', 'fastcloud-offload-media') }}</p>
        </div>
        <Switch
            v-model="fastcloudwp.state.settings.remove_original"
            @update:modelValue="updateSettings"
            :class="(fastcloudwp.state.settings.remove_original && fastcloudwp.state.settings.enabled) ? 'tw:bg-blue-500' : 'tw:bg-gray-400'"
            class="tw:relative tw:inline-flex tw:h-6 tw:w-11 tw:items-center tw:rounded-full"
        >
          <span class="tw:sr-only">{{ __('Remove original media', 'fastcloud-offload-media') }}</span>
          <span
              :class="(fastcloudwp.state.settings.remove_original && fastcloudwp.state.settings.enabled) ? 'tw:translate-x-6' : 'tw:translate-x-1'"
              class="tw:inline-block tw:h-4 tw:w-4 tw:transform tw:rounded-full tw:bg-white tw:transition"
          />
        </Switch>
      </div>
      <div class="tw:p-6 tw:flex tw:gap-4 tw:justify-between tw:items-center">
        <div class="tw:w-full tw:max-w-[580px]">
          <p class="tw:font-medium tw:text-[14px] tw:text-red-700">{{ __('Disconnect Website', 'fastcloud-offload-media') }}</p>
          <p class="tw:text-neutral-400 tw:mb-2">{{ __('If local media files have been removed, disconnecting FastCloudWP may cause broken URLs or missing images unless those files are restored locally. Your files on FastCloudWP are not deleted.', 'fastcloud-offload-media') }}</p>
        </div>
        <Button @click.prevent="disconnect" variant="danger">{{ __('Disconnect', 'fastcloud-offload-media') }}</Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import {computed, ref} from "vue";
import {useFastCloud} from "../state.ts";
import {apiFetch} from "../utils.ts";
import type {ApiSettingsResponse, ApiStateResponse} from "../types";
import {useRouter} from "vue-router";
import {Switch} from "@headlessui/vue";
import Input from "../components/ui/Input.vue";
import Button from "../components/ui/Button.vue";
import {__} from '@wordpress/i18n';

const fastcloudwp = useFastCloud();

const router = useRouter();

const copied = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | null = null

const copySiteKey = async () => {
  if (!fastcloudwp.value.state.sitekey) {
    return
  }

  await navigator.clipboard.writeText(fastcloudwp.value.state.sitekey)

  copied.value = true

  if (copiedTimer) {
    clearTimeout(copiedTimer)
  }

  copiedTimer = setTimeout(() => {
    copied.value = false
    copiedTimer = null
  }, 2000)
}

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

const disconnect = async () => {
  if (confirm(__('Are you sure you want to disconnect FastCloudWP?', 'fastcloud-offload-media'))) {
    const {data} = await apiFetch<ApiStateResponse>('/wp-json/fastcloudwp/v1/disconnect', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
    })

    Object.assign(fastcloudwp.value, data.state);

    await router.push('/');
  }
}

const storage = computed(() => fastcloudwp.value.state.storage);

const storagePercent = computed(() => storage.value?.percent_used ?? 0);

const storageBarColor = computed(() => {
  if (storage.value?.exceeded || storagePercent.value >= 95) return 'tw:bg-red-500';
  if (storagePercent.value >= 75) return 'tw:bg-yellow-500';
  return 'tw:bg-blue-500';
});

const formatBytes = (bytes: number): string => {
  if (!bytes) return '0 KB';
  const gb = bytes / (1024 ** 3);
  if (gb >= 1) return `${gb.toFixed(2)} GB`;
  const mb = bytes / (1024 ** 2);
  if (mb >= 1) return `${mb.toFixed(2)} MB`;
  const kb = bytes / 1024;
  return `${kb.toFixed(2)} KB`;
};
</script>
