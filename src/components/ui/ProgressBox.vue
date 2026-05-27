<template>
  <div
      class="tw:rounded-md tw:border tw:px-4 tw:pt-3 tw:pb-4"
      :class="{'tw:text-primary tw:border-primary': totalPending > 0 || inProgress, 'tw:text-[#13AE16] tw:border-[#13AE16]': totalPending <= 0 && !inProgress, 'tw:pt-4': inProgress}"
  >
    <template v-if="!inProgress">
      <div class="tw:flex tw:items-center tw:gap-2">
        <div class="tw:shrink-0 tw:pt-1">
          <svg v-if="totalPending > 0" width="24" height="24" viewBox="0 0 32 32" fill="none"
               xmlns="http://www.w3.org/2000/svg">
            <path
                d="M17.3346 17.3334H14.668V9.33341H17.3346M17.3346 22.6667H14.668V20.0001H17.3346M16.0013 2.66675C14.2503 2.66675 12.5165 3.01162 10.8989 3.68169C9.28118 4.35175 7.81133 5.33388 6.57321 6.57199C4.07273 9.07248 2.66797 12.4639 2.66797 16.0001C2.66797 19.5363 4.07273 22.9277 6.57321 25.4282C7.81133 26.6663 9.28118 27.6484 10.8989 28.3185C12.5165 28.9885 14.2503 29.3334 16.0013 29.3334C19.5375 29.3334 22.9289 27.9287 25.4294 25.4282C27.9299 22.9277 29.3346 19.5363 29.3346 16.0001C29.3346 14.2491 28.9898 12.5153 28.3197 10.8976C27.6496 9.27996 26.6675 7.81011 25.4294 6.57199C24.1913 5.33388 22.7214 4.35175 21.1037 3.68169C19.4861 3.01162 17.7523 2.66675 16.0013 2.66675Z"
                fill="#1B65D8"/>
          </svg>
          <svg v-else width="24" height="24" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M10.0013 1.6665C5.41797 1.6665 1.66797 5.4165 1.66797 9.99984C1.66797 14.5832 5.41797 18.3332 10.0013 18.3332C14.5846 18.3332 18.3346 14.5832 18.3346 9.99984C18.3346 5.4165 14.5846 1.6665 10.0013 1.6665ZM8.33464 14.1665L4.16797 9.99984L5.34297 8.82484L8.33464 11.8082L14.6596 5.48317L15.8346 6.6665L8.33464 14.1665Z"
                fill="#13AE16"/>
          </svg>
        </div>
        <p class="tw:text-[16px] tw:m-0">
          <template v-if="totalPending > 0"><strong v-if="count > 0">{{ count }}&nbsp;</strong>{{ text }}</template>
          <template v-else>{{ success }}</template>
        </p>
      </div>
      <Button @click="emit('run')" v-if="count > 0" class="tw:mt-4 tw:w-full">{{ button }}</Button>
    </template>
    <div v-else>
      <slot name="progress"/>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Button from "./Button.vue";
import {computed} from "vue";

const props = withDefaults(defineProps<{
  inProgress?: boolean;
  count: number;
  queued?: number;
  text: string;
  success?: string;
  button: string;
}>(), {
  inProgress: false,
  queued: 0
});

const totalPending = computed(() => props.count + props.queued);

const emit = defineEmits<{
  run: [];
}>();
</script>
