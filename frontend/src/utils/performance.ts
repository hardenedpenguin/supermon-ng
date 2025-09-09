// Performance optimization utilities

import { ref, onMounted, onUnmounted } from 'vue'
import type { Ref } from 'vue'

/**
 * Debounce function for performance optimization
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: NodeJS.Timeout | undefined

  return function executedFunction(...args: Parameters<T>) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }

    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

/**
 * Throttle function for performance optimization
 */
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  limit: number
): (...args: Parameters<T>) => void {
  let inThrottle: boolean

  return function executedFunction(...args: Parameters<T>) {
    if (!inThrottle) {
      func.apply(this, args)
      inThrottle = true
      setTimeout(() => (inThrottle = false), limit)
    }
  }
}

/**
 * Intersection Observer composable for lazy loading
 */
export function useIntersectionObserver(
  target: Ref<Element | null>,
  callback: IntersectionObserverCallback,
  options: IntersectionObserverInit = {}
) {
  const observer = ref<IntersectionObserver | null>(null)

  const defaultOptions: IntersectionObserverInit = {
    root: null,
    rootMargin: '0px',
    threshold: 0.1,
    ...options
  }

  onMounted(() => {
    if (target.value) {
      observer.value = new IntersectionObserver(callback, defaultOptions)
      observer.value.observe(target.value)
    }
  })

  onUnmounted(() => {
    if (observer.value) {
      observer.value.disconnect()
    }
  })

  return {
    observer
  }
}

/**
 * Virtual scrolling helper for large lists
 */
export function useVirtualScroll<T>(
  items: Ref<T[]>,
  itemHeight: number,
  containerHeight: number,
  overscan: number = 5
) {
  const scrollTop = ref(0)
  const containerRef = ref<HTMLElement | null>(null)

  const visibleRange = computed(() => {
    const start = Math.floor(scrollTop.value / itemHeight)
    const end = Math.min(
      start + Math.ceil(containerHeight / itemHeight) + overscan,
      items.value.length
    )
    
    return {
      start: Math.max(0, start - overscan),
      end
    }
  })

  const visibleItems = computed(() => {
    const { start, end } = visibleRange.value
    return items.value.slice(start, end).map((item, index) => ({
      item,
      index: start + index
    }))
  })

  const totalHeight = computed(() => items.value.length * itemHeight)

  const onScroll = throttle((event: Event) => {
    const target = event.target as HTMLElement
    scrollTop.value = target.scrollTop
  }, 16)

  return {
    containerRef,
    visibleItems,
    totalHeight,
    visibleRange,
    onScroll
  }
}

/**
 * Memory usage monitoring
 */
export function useMemoryMonitor() {
  const memoryInfo = ref<any>(null)

  const updateMemoryInfo = () => {
    if ('memory' in performance) {
      memoryInfo.value = {
        usedJSHeapSize: (performance as any).memory.usedJSHeapSize,
        totalJSHeapSize: (performance as any).memory.totalJSHeapSize,
        jsHeapSizeLimit: (performance as any).memory.jsHeapSizeLimit
      }
    }
  }

  const startMonitoring = (interval: number = 5000) => {
    updateMemoryInfo()
    const intervalId = setInterval(updateMemoryInfo, interval)
    
    onUnmounted(() => {
      clearInterval(intervalId)
    })

    return intervalId
  }

  return {
    memoryInfo,
    updateMemoryInfo,
    startMonitoring
  }
}

/**
 * Performance timing utilities
 */
export class PerformanceTimer {
  private marks: Map<string, number> = new Map()

  mark(name: string): void {
    this.marks.set(name, performance.now())
  }

  measure(startMark: string, endMark?: string): number {
    const start = this.marks.get(startMark)
    if (!start) {
      throw new Error(`Mark "${startMark}" not found`)
    }

    const end = endMark ? this.marks.get(endMark) : performance.now()
    if (endMark && !end) {
      throw new Error(`Mark "${endMark}" not found`)
    }

    return (end as number) - start
  }

  clear(name?: string): void {
    if (name) {
      this.marks.delete(name)
    } else {
      this.marks.clear()
    }
  }

  getAllMarks(): Record<string, number> {
    return Object.fromEntries(this.marks)
  }
}

/**
 * Image lazy loading utility
 */
export function useLazyImage(src: Ref<string>) {
  const imageRef = ref<HTMLImageElement | null>(null)
  const isLoaded = ref(false)
  const isError = ref(false)

  const { observer } = useIntersectionObserver(
    imageRef,
    ([entry]) => {
      if (entry.isIntersecting && imageRef.value) {
        const img = imageRef.value
        img.src = src.value
        
        img.onload = () => {
          isLoaded.value = true
        }
        
        img.onerror = () => {
          isError.value = true
        }

        observer.value?.unobserve(img)
      }
    }
  )

  return {
    imageRef,
    isLoaded,
    isError
  }
}

// Add computed import
import { computed } from 'vue'
