<template>
  <div class="tw:bg-white tw:border tw:border-neutral-200 tw:rounded-lg tw:shadow-sm tw:max-w-[560px]">

    <!-- ── Hero ── -->
    <div class="tw:flex tw:flex-col tw:items-center tw:text-center tw:pt-8 tw:pb-6 tw:px-8 tw:border-b tw:border-b-neutral-200">
      <img src="../img/icon.svg" alt="" width="100" height="52" class="tw:mb-4"/>
      <h2 class="tw:text-xl tw:font-bold tw:m-0 tw:mb-2">{{ __('Get started with FastCloudWP', 'fastcloud-offload-media') }}</h2>
      <p class="tw:m-0 tw:text-neutral-500 tw:text-sm">{{ __('Offload and serve your media from a global CDN.', 'fastcloud-offload-media') }}<br>{{ __('Setup takes less than 2 minutes.', 'fastcloud-offload-media') }}</p>
    </div>


    <!-- ── Body ── -->
    <div class="tw:px-8 tw:py-6 tw:flex tw:flex-col tw:gap-5">

      <!-- Email registration form -->
      <form method="post" @submit.prevent="register">
        <div class="tw:space-y-3">
          <Notice variant="info">{{ __("Offloading starts right away. We'll also send you a link to finish your account setup.", 'fastcloud-offload-media') }}</Notice>
          <div>
            <label class="tw:font-semibold tw:text-sm tw:block tw:mb-1" for="email">{{ __('Email address', 'fastcloud-offload-media') }}</label>
            <Input :error="registerError" v-model="email" type="email" name="email" id="email" placeholder="you@example.com" :required="true"/>
          </div>
          <Button type="submit" size="lg" class="tw:w-full" :loading="registering">
            {{ registering ? __('Starting…', 'fastcloud-offload-media') : __('Start offloading', 'fastcloud-offload-media') }}
          </Button>
          <p class="tw:m-0 tw:text-center tw:text-xs tw:text-neutral-400">
            {{ __('By continuing, you agree to our', 'fastcloud-offload-media') }}
            <a href="https://fastcloudwp.com/privacy" target="_blank" class="tw:underline tw:text-neutral-500 hover:tw:text-neutral-700">{{ __('Privacy Policy', 'fastcloud-offload-media') }}</a>
            {{ __('and', 'fastcloud-offload-media') }}
            <a href="https://fastcloudwp.com/terms" target="_blank" class="tw:underline tw:text-neutral-500 hover:tw:text-neutral-700">{{ __('Terms of Service', 'fastcloud-offload-media') }}</a>.
          </p>
        </div>
      </form>

      <!-- "already have a site key?" divider -->
      <div class="tw:flex tw:items-center tw:gap-3">
        <div class="tw:flex-1 tw:h-px tw:bg-neutral-200"></div>
        <span class="tw:text-xs tw:text-neutral-400">{{ __('already have a site key?', 'fastcloud-offload-media') }}</span>
        <div class="tw:flex-1 tw:h-px tw:bg-neutral-200"></div>
      </div>

      <!-- Site key form -->
      <form method="post" @submit.prevent="submit">
        <div class="tw:space-y-3">
          <div>
            <label class="tw:font-semibold tw:text-sm tw:block tw:mb-1" for="sitekey">{{ __('Site key', 'fastcloud-offload-media') }}</label>
            <p class="tw:m-0 tw:mb-2 tw:text-xs tw:text-neutral-500">{{ __('Found in your FastCloudWP dashboard under your site settings.', 'fastcloud-offload-media') }}</p>
            <Input :error="connectError" v-model="fastcloudwp.state.sitekey" name="sitekey" placeholder="sk-xxxxxxxxxxxxxxxxxx-xxxxxxxx" :required="true"/>
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
const router = useRouter();

const email = ref(fastcloudwp.value.adminEmail ?? '');
const registering = ref(false);
const registerError = ref<string | undefined>();

const connectError = ref<string | undefined>();
const connecting = ref(false);

const register = async () => {
  registering.value = true;
  registerError.value = undefined;

  try {
    const {response, data} = await apiFetch<ApiStateResponse>('/wp-json/fastcloudwp/v1/register', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({email: email.value}),
    });

    if (!response.ok) {
      registerError.value = data.error;
      return;
    }

    Object.assign(fastcloudwp.value, data.state);

    await router.push('/dashboard');
  } finally {
    registering.value = false;
  }
}

const submit = async () => {
  connecting.value = true;
  connectError.value = undefined;

  try {
    const {response, data} = await apiFetch<ApiStateResponse>('/wp-json/fastcloudwp/v1/connect', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({sitekey: fastcloudwp.value.state.sitekey}),
    });

    if (!response.ok) {
      connectError.value = data.error;
      return;
    }

    Object.assign(fastcloudwp.value, data.state);

    await router.push('/dashboard');
  } finally {
    connecting.value = false;
  }
}
</script>
