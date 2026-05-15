<template>
  <div class="tw:grow tw:flex tw:flex-col tw-fastcloud-reset">
    <Header/>

    <div class="tw:px-6 tw-fastcloud-reset tw:flex tw:items-center tw:grow tw:justify-center">
      <div class="wrap">
        <slot/>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>

import Header from "../components/Header.vue";
</script>
