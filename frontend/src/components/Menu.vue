<template>
  <div id="menu" v-if="menuItems && Object.keys(menuItems).length > 0">
    <ul>
      <!-- Main menu items (horizontal) - integrated directly -->
      <li 
        v-for="item in (menuItems.mainItems || [])" 
        :key="item.name"
      >
        <a 
          href="#"
          @click="handleMenuClick(item, $event)"
        >
          {{ item.name }}
        </a>
      </li>
      
      <!-- Dropdown menus for other systems -->
      <li 
        v-for="(items, systemName) in getSystemMenus()" 
        :key="systemName"
        class="dropdown"
      >
        <a href="#" class="dropbtn">{{ systemName }}</a>
        <div class="dropdown-content">
          <a 
            v-for="item in items" 
            :key="item.name"
            href="#"
            @click="handleMenuClick(item, $event)"
          >
            {{ item.name }}
          </a>
        </div>
      </li>
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

interface MenuItems {
  [systemName: string]: MenuItem[]
}

const appStore = useAppStore()
const menuItems = ref<MenuItems>({})
const isLoading = ref(false)

// URL Modal state
const showUrlModal = ref(false)
const currentUrl = ref('')
const currentUrlTitle = ref('')

// Lsnod Modal state
const showLsnodModal = ref(false)
const currentLsnodNodeId = ref('')

const loadMenu = async () => {
  try {
    isLoading.value = true
    const response = await api.get('/config/menu')
    if (response.data.success) {
      // Only update menu items if we got valid data
      if (response.data.data && Object.keys(response.data.data).length > 0) {
        menuItems.value = response.data.data
      }
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
  // Prevent default link behavior
  event.preventDefault()
  
  // Handle internal navigation if needed
  if (item.url.startsWith('link.php') || item.url.startsWith('voter.php')) {
    // Parse node selection from URL
    const urlParams = new URLSearchParams(item.url.split('?')[1])
    const nodes = urlParams.get('nodes')
    if (nodes) {
      emit('nodeSelection', nodes)
    }
  } else if (item.url.startsWith('/lsnod/')) {
    // Handle lsnod URLs - open in modal
    const nodeId = item.url.replace('/lsnod/', '')
    currentLsnodNodeId.value = nodeId
    showLsnodModal.value = true
  } else if (item.url.startsWith('http')) {
    // External links - check if they should open in new tab or modal
    if (item.targetBlank) {
      // Open in new tab/window as intended
      window.open(item.url, '_blank', 'noopener,noreferrer')
    } else {
      // Open in modal for internal dashboard experience
      currentUrl.value = item.url
      currentUrlTitle.value = item.name
      showUrlModal.value = true
    }
  } else {
    // For other internal links, just emit the nodeSelection if it's a node-related URL
    // This handles cases where the URL might contain node information in other formats
    if (item.url.includes('nodes=')) {
      const urlParams = new URLSearchParams(item.url.split('?')[1])
      const nodes = urlParams.get('nodes')
      if (nodes) {
        emit('nodeSelection', nodes)
      }
    }
  }
}

const getSystemMenus = () => {
  const systemMenus: MenuItems = {}
  for (const [key, value] of Object.entries(menuItems.value)) {
    if (key !== 'mainItems') {
      systemMenus[key] = value
    }
  }
  return systemMenus
}

onMounted(() => {
  loadMenu()
})

// Watch for authentication changes and reload menu with a small delay
watch(() => appStore.isAuthenticated, () => {
  // Add a small delay to prevent menu flash when authentication state changes
  setTimeout(() => {
    loadMenu()
  }, 100)
})
</script>

<style scoped>
#menu {
  width: calc(100% + 40px); /* Match header width - extend beyond dashboard padding */
  margin-left: -20px; /* Offset the dashboard padding */
  margin-right: -20px; /* Offset the dashboard padding */
  background-color: var(--menu-background, #2a2a2a);
  border-radius: 5px;
  margin-bottom: 20px;
  display: flex;
  justify-content: center;
  align-items: center;
  /* Ensure dropdown overlays subsequent content */
  position: relative;
  z-index: 100;
  /* Allow dropdown to extend beyond menu boundaries */
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
  /* Lift dropdown above buttons/content below */
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

/* Responsive Menu Styles */
@media (max-width: 768px) {
  #menu {
    width: calc(100% + 20px); /* Adjust for mobile padding (10px each side) */
    margin-left: -10px; /* Offset mobile padding */
    margin-right: -10px; /* Offset mobile padding */
  }
  
  #menu ul {
    flex-direction: column;
  }
  
  #menu .dropdown-content {
    position: static;
    display: none;
  }
  
  #menu .dropdown:hover .dropdown-content {
    display: block;
  }
}

/* Extra small screens */
@media (max-width: 480px) {
  #menu {
    width: calc(100% + 10px); /* Adjust for extra small padding */
    margin-left: -5px;
    margin-right: -5px;
  }
  
  #menu li a {
    padding: 6px 10px; /* Reduce padding on small screens */
    font-size: 0.9em;
  }
  
  #menu .dropdown-content a {
    padding: 10px 14px;
    font-size: 0.9em;
  }
}

/* Print Menu Styles */
@media print {
  #menu {
    display: none;
  }
}
</style>
