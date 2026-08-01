import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { state } from './state.ts'

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs))
}

export async function apiFetch<T = unknown>(url: string, options: RequestInit = {}): Promise<{ response: Response; data: T }> {
    const { headers: callerHeaders, ...rest } = options

    const headers = new Headers(callerHeaders as HeadersInit)
    headers.set('X-WP-Nonce', state.value.nonce)

    const response = await fetch(url, { credentials: 'include', headers, ...rest })
    const data = (await response.json()) as T

    if (response.headers.get('X-FastCloud-Connected') === '0') {
        state.value.state.connected = false
    }

    return { response, data }
}
