<template>
  <Panel :count="fastcloudwp.statistics.deleted" :stats="fastcloudwp.statistics.deleted_progress"
         :title="__('Local copies freed', 'fastcloud-offload-media')">
    <div
        class="tw:w-full tw:flex tw:items-center tw:flex-col tw:justify-center tw:border-b tw:border-b-neutral-300 tw:pb-6">
      <CircleStat
          :count="fastcloudwp.statistics.deleted"
          :total="fastcloudwp.statistics.offloaded"
          :stats="fastcloudwp.statistics.deleted_progress"/>
      <p class="tw:text-[16px]"><strong class="tw:font-extrabold">
        {{ fastcloudwp.statistics.deleted }}</strong> {{ __('of', 'fastcloud-offload-media') }} {{ fastcloudwp.statistics.offloaded }} {{ __('files freed', 'fastcloud-offload-media') }}
      </p>
    </div>
    <div class="tw:mt-4 tw:flex tw:gap-4 tw:justify-between tw:items-center">
      <p class="tw:flex tw:items-center tw:gap-2">
        <svg width="24" height="24" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
              d="M18.668 18.6667H21.3346L16.0013 13.3333L10.668 18.6667H13.3346V24H18.668V18.6667ZM8.0013 9.33333H24.0013V25.3333C24.0013 26 23.7346 26.6667 23.188 27.1867C22.668 27.7333 22.0013 28 21.3346 28H10.668C10.0013 28 9.33464 27.7333 8.81464 27.1867C8.26797 26.6667 8.0013 26 8.0013 25.3333V9.33333ZM25.3346 5.33333V8H6.66797V5.33333H11.3346L12.668 4H19.3346L20.668 5.33333H25.3346Z"
              fill="#1B65D8"/>
        </svg>
        <span>{{ __('Auto-cleaning', 'fastcloud-offload-media') }}</span>
      </p>
      <div class="tw:flex tw:items-center tw:gap-1">
        <div class="tw:flex tw:order-3 tw:items-center tw:gap-2">
          <Pulse :variant="fastcloudwp.state.settings.delete_media ? 'default' : 'disabled'"/>
          <span :class="fastcloudwp.state.settings.delete_media ? 'tw:font-semibold tw:text-green-600' : 'tw:font-semibold tw:text-neutral-500'">{{ fastcloudwp.state.settings.delete_media ? __('Active', 'fastcloud-offload-media') : __('Disabled', 'fastcloud-offload-media') }}</span>
        </div>
      </div>
    </div>
    <ProgressBox
        v-if="fastcloudwp.state.settings.enabled && fastcloudwp.statistics.total > 0 && fastcloudwp.statistics.offloaded > 0"
        class="tw:mt-6"
        :in-progress="deleting"
        :count="fastcloudwp.statistics.pending_delete"
        :success="__('All offloaded media has been removed from your server. Enjoy your fast website.', 'fastcloud-offload-media')"
        :text="__('Local copies have not been freed and need manual action from you.', 'fastcloud-offload-media')"
        :button="__('Delete Local Copies', 'fastcloud-offload-media')"
        @run="freeSpace">
      <template #progress>
        <Progress :value="deleteProgress"/>
        <p v-if="!errorBatching" class="tw:mt-0 tw:text-neutral-500">{{ queueLabel }}</p>
        <p class="tw:text-red-800" v-else>
          {{ __('Something went wrong while freeing local copies.', 'fastcloud-offload-media') }}</p>
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
import type {ApiFreeSpaceResponse} from "../../types";
import Progress from "../Progress.vue";
import ProgressBox from "../ui/ProgressBox.vue";
import CircleStat from "../ui/CircleStat.vue";
import Panel from "../Panel.vue";
import {__, sprintf} from '@wordpress/i18n';
import Pulse from "../ui/Pulse.vue";

const fastcloudwp = useFastCloud();

const deleting = ref(false)
const deleteDone = ref(false)
const deleteCancelled = ref(false)
const errorBatching = ref(false)

const queueTotal = ref(0)
const queueLeft = ref(0)
const mediaDeleted = ref(0)

let abortController: AbortController | null = null

const deleteProgress = computed(() => {
  if (queueTotal.value === 0) return deleteDone.value ? 100 : 0
  return Math.round(((queueTotal.value - queueLeft.value) / queueTotal.value) * 100)
})

const queueLabel = computed(() => {
  /* translators: %d: number of local files deleted */
  return sprintf(__('%d local copies deleted\u2026', 'fastcloud-offload-media'), mediaDeleted.value)
})

const canClose = computed(() => deleteDone.value || errorBatching.value || deleteCancelled.value)

function handleProgressButton() {
  if (canClose.value) {
    deleting.value = false
    deleteDone.value = false
    deleteCancelled.value = false
    errorBatching.value = false
  } else {
    abortController?.abort()
    deleteCancelled.value = true
  }
}

async function freeSpace() {
  if (deleting.value) return

  deleting.value = true
  deleteDone.value = false
  deleteCancelled.value = false
  errorBatching.value = false
  queueTotal.value = fastcloudwp.value.statistics.pending_delete
  queueLeft.value = fastcloudwp.value.statistics.pending_delete
  mediaDeleted.value = 0

  try {
    do {
      abortController = new AbortController()

      const {data: batchData} = await apiFetch<ApiFreeSpaceResponse>('/wp-json/fastcloudwp/v1/free-space', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        signal: abortController.signal,
      })

      mediaDeleted.value += batchData.deleted ?? 0
      queueLeft.value = batchData.remaining ?? 0

      if (!batchData.success) {
        errorBatching.value = true
        break
      }

      if (batchData.done || queueLeft.value <= 0) {
        deleteDone.value = true
        break
      }
    } while (!deleteCancelled.value && queueLeft.value > 0)
  } catch (e) {
    if (!(e instanceof DOMException && e.name === 'AbortError')) {
      errorBatching.value = true
    }
  } finally {
    abortController = null
  }
}
</script>
