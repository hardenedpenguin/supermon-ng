import { ref, onMounted, onUnmounted, type Ref } from 'vue'

/**
 * Shared 1-second ticker.
 *
 * Each NodeTable used to create its own setInterval, so a multi-node view ran N
 * timers that all mutated their own tick and forced re-render storms. This
 * exposes a single module-level interval, reference-counted across all
 * consumers, so there is exactly one tick per second no matter how many tables
 * are mounted.
 */
const timerTick = ref(0)
let intervalId: ReturnType<typeof setInterval> | null = null
let subscribers = 0

function start(): void {
  subscribers++
  if (intervalId === null) {
    intervalId = setInterval(() => {
      timerTick.value = Date.now()
    }, 1000)
  }
}

function stop(): void {
  subscribers = Math.max(0, subscribers - 1)
  if (subscribers === 0 && intervalId !== null) {
    clearInterval(intervalId)
    intervalId = null
  }
}

export function useTimerTick(): { timerTick: Ref<number> } {
  onMounted(start)
  onUnmounted(stop)
  return { timerTick }
}
