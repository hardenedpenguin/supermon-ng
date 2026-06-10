<template>
  <div id="menu" v-if="menuSections.length > 0">
    <ul>
      <template v-for="section in menuSections" :key="sectionKey(section)">
        <li v-if="section.type === 'link'">
          <a
            href="#"
            @click="handleMenuClick(section, $event)"
          >
            {{ section.name }}
          </a>
        </li>
        <li v-else class="dropdown">
          <a href="#" class="dropbtn">{{ section.label }}</a>
          <div class="dropdown-content">
            <a
              v-for="item in section.items"
              :key="item.name"
              href="#"
              @click="handleMenuClick(item, $event)"
            >
              {{ item.name }}
            </a>
          </div>
        </li>
      </template>
    </ul>
  </div>

  <!-- URL Modal for external links -->
  <UrlModal
    v-model:isVisible="showUrlModal"
    :url="currentUrl"
    :title="currentUrlTitle"
  />

  <!-- Lsnod Modal for lsnod data -->
  <LsnodModal
    v-model:isVisible="showLsnodModal"
    :nodeId="currentLsnodNodeId"
  />
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { api } from '@/utils/api'
import { useAppStore } from '@/stores/app'
import UrlModal from './UrlModal.vue'
import LsnodModal from './LsnodModal.vue'

interface MenuItem {
  name: string
  url: string
  targetBlank: boolean
}

interface MenuLinkSection extends MenuItem {
  type: 'link'
}

interface MenuDropdownSection {
  type: 'dropdown'
  label: string
  items: MenuItem[]
}

type MenuSection = MenuLinkSection | MenuDropdownSection

/** Legacy API shape before ordered sections */
interface LegacyMenuItems {
  mainItems?: MenuItem[]
  [systemName: string]: MenuItem[] | undefined
}

const appStore = useAppStore()
const menuSections = ref<MenuSection[]>([])
const isLoading = ref(false)

// URL Modal state
const showUrlModal = ref(false)
const currentUrl = ref('')
const currentUrlTitle = ref('')

// Lsnod Modal state
const showLsnodModal = ref(false)
const currentLsnodNodeId = ref('')

const sectionKey = (section: MenuSection): string => {
  if (section.type === 'link') {
    return `link-${section.name}`
  }
  return `dropdown-${section.label}`
}

const legacyToSections = (legacy: LegacyMenuItems): MenuSection[] => {
  const sections: MenuSection[] = []
  const nodeLabels = new Set(['nodes', 'display groups'])
  const nodeGroups: MenuDropdownSection[] = []
  const rest: MenuSection[] = []

  for (const [key, items] of Object.entries(legacy)) {
    if (key === 'mainItems' || !Array.isArray(items)) {
      continue
    }
    const dropdown: MenuDropdownSection = {
      type: 'dropdown',
      label: key,
      items,
    }
    if (nodeLabels.has(key.toLowerCase())) {
      nodeGroups.push(dropdown)
    } else {
      rest.push(dropdown)
    }
  }

  if (legacy.mainItems?.length) {
    for (const item of legacy.mainItems) {
      rest.push({ type: 'link', ...item })
    }
  }

  return [...nodeGroups, ...rest]
}

const applyMenuData = (data: { sections?: MenuSection[] } & LegacyMenuItems) => {
  if (data.sections?.length) {
    menuSections.value = data.sections
    return
  }
  menuSections.value = legacyToSections(data)
}

const loadMenu = async (forceFetch = false) => {
  if (!forceFetch) {
    const bootstrapMenu = appStore.bootstrapData?.menu
    if (bootstrapMenu) {
      applyMenuData(bootstrapMenu as { sections?: MenuSection[] } & LegacyMenuItems)
      return
    }
  }

  try {
    isLoading.value = true
    const response = await api.get('/config/menu')
    if (response.data.success && response.data.data) {
      applyMenuData(response.data.data)
    }
  } catch (error) {
    console.error('Failed to load menu:', error)
  } finally {
    isLoading.value = false
  }
}

const emit = defineEmits<{
  nodeSelection: [nodeSelection: string]
}>()

const handleMenuClick = (item: MenuItem, event: Event) => {
  event.preventDefault()

  if (item.url.startsWith('link.php') || item.url.startsWith('voter.php')) {
    const urlParams = new URLSearchParams(item.url.split('?')[1])
    const nodes = urlParams.get('nodes')
    if (nodes) {
      emit('nodeSelection', nodes)
    }
  } else if (item.url.startsWith('/lsnod/')) {
    const nodeId = item.url.replace('/lsnod/', '')
    currentLsnodNodeId.value = nodeId
    showLsnodModal.value = true
  } else if (item.url.startsWith('http')) {
    if (item.targetBlank) {
      window.open(item.url, '_blank', 'noopener,noreferrer')
    } else {
      currentUrl.value = item.url
      currentUrlTitle.value = item.name
      showUrlModal.value = true
    }
  } else if (item.url.includes('nodes=')) {
    const urlParams = new URLSearchParams(item.url.split('?')[1])
    const nodes = urlParams.get('nodes')
    if (nodes) {
      emit('nodeSelection', nodes)
    }
  }
}

onMounted(async () => {
  await appStore.waitUntilInitialized()
  void loadMenu()
})

watch(() => appStore.isAuthenticated, () => {
  setTimeout(() => {
    loadMenu(true)
  }, 100)
})
</script>

<style scoped>
#menu {
  width: calc(100% + 40px);
  margin-left: -20px;
  margin-right: -20px;
  background-color: var(--menu-background, #2a2a2a);
  border-radius: 5px;
  margin-bottom: 20px;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  z-index: 100;
  overflow: visible;
  box-sizing: border-box;
}

#menu ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  width: 100%;
}

#menu li {
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
}

#menu li a,
#menu li a:link,
#menu li a:visited {
  display: inline-block;
  color: var(--text-color, #e0e0e0);
  text-align: center;
  padding: 8px 12px;
  text-decoration: none;
  border-radius: 5px;
  transition: background-color 0.3s ease;
  margin: 2px;
}

#menu li a:hover {
  background-color: var(--border-color, rgba(64, 64, 64, 0.5));
  color: var(--primary-color, #e0e0e0);
}

#menu li.dropdown {
  position: relative;
  display: block;
  overflow: visible;
}

#menu .dropdown-content {
  display: none;
  position: absolute;
  background-color: var(--container-bg, #2a2a2a);
  min-width: 160px;
  box-shadow: var(--shadow-lg, 0 10px 15px -3px rgba(0, 0, 0, 0.3));
  border: 1px solid var(--border-color, #404040);
  z-index: 999;
  border-radius: 5px;
  top: 100%;
  left: 0;
}

#menu .dropdown-content a {
  color: var(--text-color, #e0e0e0);
  padding: 12px 16px;
  text-decoration: none;
  display: block;
}

#menu .dropdown-content a:hover {
  background-color: var(--border-color, rgba(64, 64, 64, 0.5));
  color: var(--primary-color, #e0e0e0);
}

#menu .dropdown:hover .dropdown-content {
  display: block;
}

@media print {
  #menu {
    display: none;
  }
}
</style>
