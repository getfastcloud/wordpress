<template>
  <component :is="layout">
    <RouterView v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <keep-alive>
          <component :is="Component" />
        </keep-alive>
      </transition>
    </RouterView>
  </component>
</template>


<script setup lang="ts">
import AppLayout from "./layouts/AppLayout.vue";
import {RouterView, useRoute, useRouter} from "vue-router";
import {computed, watch} from "vue";
import {state} from "./state.ts";

const route = useRoute();
const router = useRouter();

const layout = computed(() => {
  return route.meta.layout || AppLayout
})

watch(() => state.value.state.connected, async (connected) => {
  if (!connected && router.currentRoute.value.path !== '/') {
    await router.push('/')
  }
})
</script>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity .1s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.tw-fastcloud-reset button {
  all: unset;
  box-sizing: border-box;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
}

.tw-fastcloud-reset * {
  box-sizing: border-box;
}

#wpbody-content {
  display: flex;
  flex-direction: column;
  padding-bottom: 0;
}

#fastcloudwp-app {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}

#fastcloudwp-app input::placeholder {
  color: rgb(163 163 163); /* neutral-400 */
}

body {
  background-color: #F7F7F8;
}
</style>
