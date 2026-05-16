import {type Ref, ref} from 'vue'
import type {FastCloudData} from "./types";

export const state = ref<FastCloudData>(window.FastCloudWP)

export function useFastCloud(): Ref<FastCloudData> {
    return state
}
