<template>
  <Panel :meta="sprintf(__('%d pending offload', 'fastcloud-offload-media'), fastcloudwp.statistics.queued)" :title="__('Offloaded on FastCloudWP', 'fastcloud-offload-media')">
    <div class="tw:w-full tw:flex tw:items-center tw:flex-col tw:justify-center tw:border-b tw:border-b-neutral-300 tw:pb-6">
      <CircleStat
          :count="fastcloudwp.statistics.offloaded"
          :total="fastcloudwp.statistics.total"
          :stats="fastcloudwp.statistics.offloaded_progress"/>
      <p class="tw:text-[16px]"><strong class="tw:font-extrabold">
        {{ fastcloudwp.statistics.offloaded }}</strong> {{ __('of', 'fastcloud-offload-media') }} {{ fastcloudwp.statistics.total }} {{ __('files offloaded', 'fastcloud-offload-media') }}
      </p>
    </div>
    <div class="tw:mt-4 tw:flex tw:gap-4 tw:justify-between tw:items-center">
      <p class="tw:flex tw:items-center tw:gap-2">
        <svg width="24" height="24" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
              d="M29.1454 14.0001C28.1824 12.8633 26.8147 12.146 25.332 12.0001C25.332 9.40008 24.4254 7.20008 22.612 5.38675C20.7987 3.57341 18.5987 2.66675 15.9987 2.66675C13.892 2.66675 11.9987 3.33341 10.332 4.57341C8.66536 5.81341 7.5587 7.49341 6.9987 9.53341C5.33203 9.90675 3.94536 10.7734 2.89203 12.1334C1.8387 13.4934 1.33203 15.0401 1.33203 16.7734C1.33203 18.7867 2.05203 20.5067 3.4787 21.9067C4.7587 23.1467 6.26536 23.8001 7.9987 23.9334V29.3334H23.9987V24.0001H24.6654C26.332 24.0001 27.7454 23.4134 28.9187 22.2534C30.0787 21.0801 30.6654 19.6667 30.6654 18.0001C30.6654 16.4667 30.1587 15.1334 29.1454 14.0001ZM21.332 26.6667H10.6654V17.3334H21.332V26.6667ZM19.9987 20.0001H11.9987V18.6667H19.9987V20.0001ZM19.9987 22.6667H11.9987V21.3334H19.9987V22.6667ZM19.9987 25.3334H11.9987V24.0001H19.9987V25.3334Z"
              fill="#1B65D8"/>
        </svg>
        <span>{{ __('Auto-offload', 'fastcloud-offload-media') }}</span>
      </p>
      <p class="tw:flex tw:items-center tw:gap-1">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
              d="M12 18C10.4087 18 8.88258 17.3679 7.75736 16.2426C6.63214 15.1174 6 13.5913 6 12C6 11 6.25 10.03 6.7 9.2L5.24 7.74C4.42975 9.01309 3.99958 10.4909 4 12C4 14.1217 4.84286 16.1566 6.34315 17.6569C7.84344 19.1571 9.87827 20 12 20V23L16 19L12 15M12 4V1L8 5L12 9V6C13.5913 6 15.1174 6.63214 16.2426 7.75736C17.3679 8.88258 18 10.4087 18 12C18 13 17.75 13.97 17.3 14.8L18.76 16.26C19.5702 14.9869 20.0004 13.5091 20 12C20 9.87827 19.1571 7.84344 17.6569 6.34315C16.1566 4.84285 14.1217 4 12 4Z"
              fill="#363636"/>
        </svg>
        <span class="tw:font-bold">{{ sprintf(__('%d files scheduled', 'fastcloud-offload-media'), fastcloudwp.statistics.queued) }}</span>
      </p>
    </div>
    <ProgressBox
        v-if="fastcloudwp.state.settings.enabled && fastcloudwp.statistics.total > 0"
        class="tw:mt-6"
        :in-progress="offloading"
        :count="fastcloudwp.statistics.missing"
        :queued="fastcloudwp.statistics.queued"
        @run="offload"
        :success="__('All of your media files have been offloaded. Enjoy your fast website.', 'fastcloud-offload-media')"
        :text="fastcloudwp.statistics.missing > 0 ? __('Files have not been offloaded and need manual action from you.','fastcloud-offload-media') : __('All files have been queued for offloading.','fastcloud-offload-media')"
        :button="__('Schedule Offload', 'fastcloud-offload-media')">
      <template #progress>
        <Progress :value="queueProgress"/>
        <p v-if="!errorBatching" class="tw:mt-0 tw:text-neutral-500">{{ queueLabel }}</p>
        <p class="tw:text-red-800" v-else>{{ __('Something went wrong while sending files to FastCloudWP.', 'fastcloud-offload-media') }}</p>
        <Button
            @click.prevent="handleProgressButton"
            variant="outline"
            class="tw:w-full tw:mt-4"
        >
          {{ canClose ? __('Close', 'fastcloud-offload-media') : __('Cancel', 'fastcloud-offload-media') }}
        </Button>
      </template>
    </ProgressBox>
  </Panel>
</template>

<script lang="ts" setup>
import {computed, ref} from "vue";
import Button from "../ui/Button.vue";
import {useFastCloud} from "../../state.ts";
import {apiFetch} from "../../utils.ts";
import type {ApiOffloadBatchResponse} from "../../types";
import Progress from "../Progress.vue";
import ProgressBox from "../ui/ProgressBox.vue";
import CircleStat from "../ui/CircleStat.vue";
import Panel from "../Panel.vue";
import {__, sprintf} from '@wordpress/i18n';

const fastcloudwp = useFastCloud();

const offloading = ref(false)
const offloadDone = ref(false)
const offloadCancelled = ref(false)
const errorBatching = ref(false)

const queueTotal = ref(0)
const queueLeft = ref(0)
const queueQueued = ref(0)

let abortController: AbortController | null = null

const queueProgress = computed(() => {
  if (queueTotal.value === 0) return offloadDone.value ? 100 : 0
  return Math.round(((queueTotal.value - queueLeft.value) / queueTotal.value) * 100)
})

const queueLabel = computed(() => {
  /* translators: %d: number of files queued for offload */
  return sprintf(__('%d files scheduled to offload\u2026', 'fastcloud-offload-media'), queueQueued.value)
})

const canClose = computed(() => offloadDone.value || errorBatching.value || offloadCancelled.value)

function handleProgressButton() {
  if (canClose.value) {
    offloading.value = false
    offloadDone.value = false
    offloadCancelled.value = false
    errorBatching.value = false
  } else {
    abortController?.abort()
    offloadCancelled.value = true
  }
}

async function offload() {
  if (offloading.value) return

  offloading.value = true
  offloadDone.value = false
  offloadCancelled.value = false
  errorBatching.value = false
  queueTotal.value = fastcloudwp.value.statistics.missing
  queueLeft.value = fastcloudwp.value.statistics.missing
  queueQueued.value = 0

  try {
    do {
      abortController = new AbortController()

      const {data: batchData} = await apiFetch<ApiOffloadBatchResponse>('/wp-json/fastcloudwp/v1/offload', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        signal: abortController.signal,
      })

      queueQueued.value += batchData.queued ?? 0
      queueLeft.value = batchData.left ?? 0

      if (!batchData.success) {
        errorBatching.value = true
        break
      }

      if (batchData.done || queueLeft.value <= 0) {
        offloadDone.value = true
        fastcloudwp.value.statistics.queued = queueQueued.value
        break
      }
    } while (!offloadCancelled.value && queueLeft.value > 0)
  } catch (e) {
    if (!(e instanceof DOMException && e.name === 'AbortError')) {
      errorBatching.value = true
    }
  } finally {
    abortController = null
  }
}
</script>
