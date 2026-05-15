<template>
  <div class="tw:mt-4">
    <div>
      <nav aria-label="Tabs" class="tw:-mb-px tw:flex">
        <AppLink
            v-for="link in links" :key="link.to"
            ariaCurrentValue="page"
            class="tw:no-underline tw:px-4 tw:py-3 tw:text-sm tw:font-medium tw:whitespace-nowrap"
            inactiveClass="tw:border-b-2 tw:border-transparent tw:text-gray-500 tw:hover:border-gray-300 tw:hover:text-gray-700"
            activeClass="tw:border-b-2 tw:border-blue-wp tw:text-blue-wp"
            :to="link.to">
          {{ link.title }}
        </AppLink>
      </nav>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppLink from "./AppLink.vue";
import type {NavLink} from "../types";
import {computed} from "vue";
import {__} from '@wordpress/i18n';

const links = computed<NavLink[]>(() => [
  {
    to: '/dashboard',
    title: __('Dashboard', 'fastcloud-offload-media')
  },
  {
    to: '/settings',
    title: __('Settings', 'fastcloud-offload-media')
  },
])
</script>
