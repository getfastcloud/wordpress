<template>
  <header class="tw:bg-white tw:px-6 tw:pt-4 tw:border-b tw:border-neutral-200" :class="{'tw:pb-4':!nav}">
    <img v-if="nav" src="../img/logo.svg" alt="" width="250" height="33"/>
    <h1 v-else class="tw:text-xl tw:font-medium tw:m-0">FastCloudWP</h1>

    <Nav v-if="nav"/>
  </header>
</template>

<script lang="ts" setup>
import Nav from "./Nav.vue";

withDefaults(defineProps<{
  nav?: boolean;
}>(), {
  nav: false,
});
</script>
