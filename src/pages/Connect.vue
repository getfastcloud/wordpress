<template>
  <div class="tw:bg-white tw:border tw:border-neutral-200 tw:rounded-lg tw:shadow-sm tw:max-w-[560px]">

    <!-- ── Hero ── -->
    <div class="tw:flex tw:flex-col tw:items-center tw:text-center tw:pt-8 tw:pb-6 tw:px-8">
      <img src="../img/icon.svg" alt="" width="100" height="52" class="tw:mb-4"/>
      <h2 class="tw:text-xl tw:font-bold tw:m-0 tw:mb-2">{{ __('Get started with FastCloudWP', 'fastcloud-offload-media') }}</h2>
      <p class="tw:m-0 tw:text-neutral-500 tw:text-sm">{{ __('Offload and serve your media from a global CDN.', 'fastcloud-offload-media') }}<br>{{ __('Setup takes less than 2 minutes.', 'fastcloud-offload-media') }}</p>
    </div>

    <hr class="tw:m-0 tw:border-neutral-200"/>

    <!-- ── Body ── -->
    <div class="tw:px-8 tw:py-6 tw:flex tw:flex-col tw:gap-5">

      <!-- Step indicator -->
      <div class="tw:flex tw:items-center">
        <div class="tw:flex tw:items-center tw:gap-2 tw:shrink-0">
          <div class="tw:w-8 tw:h-8 tw:rounded-full tw:bg-primary tw:text-white tw:flex tw:items-center tw:justify-center tw:text-sm tw:font-semibold">1</div>
          <span class="tw:font-semibold tw:text-sm">{{ __('Create account', 'fastcloud-offload-media') }}</span>
        </div>
        <div class="tw:flex-1 tw:h-px tw:bg-neutral-300 tw:mx-4"></div>
        <div class="tw:flex tw:items-center tw:gap-2 tw:shrink-0">
          <div class="tw:w-8 tw:h-8 tw:rounded-full tw:bg-neutral-200 tw:text-neutral-400 tw:flex tw:items-center tw:justify-center tw:text-sm tw:font-semibold">2</div>
          <span class="tw:text-sm tw:text-neutral-400">{{ __('Connect site', 'fastcloud-offload-media') }}</span>
        </div>
      </div>

      <!-- Info notice -->
      <Notice variant="info">
        {{ __('Create your free FastCloudWP account to get a site key, then come back here to connect your site.', 'fastcloud-offload-media') }}
      </Notice>

      <!-- CTA: create account -->
      <Button size="lg" class="tw:w-full" as="a" href="https://app.fastcloudwp.com/register" target="_blank">
        <svg class="tw:mr-2 tw:shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
          <polyline points="15 3 21 3 21 9"/>
          <line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
        {{ __('Create free account at fastcloudwp.com', 'fastcloud-offload-media') }}
      </Button>

      <!-- "already have an account?" divider -->
      <div class="tw:flex tw:items-center tw:gap-3">
        <div class="tw:flex-1 tw:h-px tw:bg-neutral-200"></div>
        <span class="tw:text-xs tw:text-neutral-400">{{ __('already have an account?', 'fastcloud-offload-media') }}</span>
        <div class="tw:flex-1 tw:h-px tw:bg-neutral-200"></div>
      </div>

      <!-- Site key form -->
      <form method="post" @submit.prevent="submit">
        <div class="tw:space-y-3">
          <div>
            <label class="tw:font-semibold tw:text-sm tw:block tw:mb-1" for="sitekey">{{ __('Site key', 'fastcloud-offload-media') }}</label>
            <p class="tw:m-0 tw:mb-2 tw:text-sm tw:text-neutral-500">{{ __('Found in your FastCloudWP dashboard under your site settings.', 'fastcloud-offload-media') }}</p>
            <Input :error="error" v-model="fastcloudwp.state.sitekey" name="sitekey" placeholder="sk-xxxxxxxxxxxxxxxxxx-xxxxxxxx" :required="true"/>
          </div>
          <Button type="submit" class="tw:w-full" variant="secondary" :loading="connecting">
            {{ connecting ? __('Connecting…', 'fastcloud-offload-media') : __('Connect website', 'fastcloud-offload-media') }}
          </Button>
        </div>
      </form>

    </div>
  </div>
</template>

<script lang="ts" setup>
import Input from '../components/ui/Input.vue';
import Button from '../components/ui/Button.vue';
import Notice from '../components/Notice.vue';
import {useFastCloud} from '../state.ts';
import {apiFetch} from '../utils.ts';
import type {ApiStateResponse} from '../types';
import {ref} from 'vue';
import {useRouter} from 'vue-router';
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
    });

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
