<template>
  <div class="tw:max-w-full tw:mt-12">
    <div
        class="tw:bg-white tw:shadow tw:w-full tw:px-7 tw:pt-7 tw:pb-6 tw:border tw:border-neutral-200 tw:rounded-md tw:w-md tw:flex tw:flex-col tw:items-center">

      <div class="tw:text-center tw:max-w-[330px] tw:mb-8">
        <img src="../img/icon.svg" alt="" width="120" height="62"/>
        <p class="tw:text-lg tw:mt-4 tw:font-semibold tw:mb-1">{{ __('Connect your website', 'fastcloud-offload-media') }}</p>
        <p class="tw:my-0 tw:text-neutral-500">{{ __('Enter your FastCloudWP site key to enable remote media storage and optimization.', 'fastcloud-offload-media') }}</p>
      </div>

      <form class="tw:w-full" method="post" @submit.prevent="submit">
        <div class="tw:space-y-4">
          <div>
            <Input :error="error" v-model="fastcloudwp.state.sitekey" name="sitekey" :label="__('Site Key', 'fastcloud-offload-media')"
                   placeholder="sk-xxxxxxxxxxxxxxxxxx-xxxxxxxx" :required="true"/>
          </div>
          <Button type="submit" class="tw:w-full" :loading="connecting">
            {{ connecting ? __('Connecting…', 'fastcloud-offload-media') : __('Connect Website', 'fastcloud-offload-media') }}
          </Button>
        </div>
      </form>

    </div>
    <p class="tw:text-neutral-400 tw:text-center tw:w-full tw:mt-4">{{ __('No account yet? Sign up for free at', 'fastcloud-offload-media') }} <a
        target="_blank" class="tw:underline" href="https://fastcloudwp.com">fastcloudwp.com</a></p>
  </div>
</template>

<script lang="ts" setup>
import Input from "../components/ui/Input.vue";
import Button from "../components/ui/Button.vue";
import {useFastCloud} from "../state.ts";
import {apiFetch} from "../utils.ts";
import type {ApiStateResponse} from "../types";
import {ref} from "vue";
import {useRouter} from "vue-router";
import {__} from '@wordpress/i18n';

const fastcloudwp = useFastCloud();
const error = ref<string | undefined>();
const connecting = ref(false);
const router = useRouter();

const submit = async () => {
  connecting.value = true;

  try {
    const {response, data} = await apiFetch<ApiStateResponse>('/wp-json/fastcloudwp/v1/connect', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({sitekey: fastcloudwp.value.state.sitekey}),
    })

    if (!response.ok) {
      error.value = data.error;
      return;
    }

    Object.assign(fastcloudwp.value, data.state);

    await router.push('/dashboard');
  } finally {
    connecting.value = false;
  }
}
</script>
