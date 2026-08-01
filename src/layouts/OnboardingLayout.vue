<template>
  <div class="tw:grow tw:flex tw:flex-col tw-fastcloud-reset">
    <Header/>

    <div class="tw:px-6 tw:pb-8 tw-fastcloud-reset">
      <div class="wrap tw:m-0 tw:py-6">
        <slot/>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>

import Header from "../components/Header.vue";
</script>
