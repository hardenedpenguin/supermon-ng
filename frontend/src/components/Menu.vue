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
                   :target="item.targetBlank ? '_blank' : undefined"
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
                     :target="item.targetBlank ? '_blank' : undefined"
                     @click="handleMenuClick(item, $event)"
                   >
            {{ item.name }}
          </a>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { api } from '@/utils/api'
import { useAppStore } from '@/stores/app'

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

const loadMenu = async () => {
  try {
    const response = await api.get('/config/menu')
    console.log('🔍 Menu API response:', response.data)
    if (response.data.success) {
      menuItems.value = response.data.data
      console.log('🔍 Loaded menu items:', menuItems.value)
    }
  } catch (error) {
    console.error('Failed to load menu:', error)
  }
}

const emit = defineEmits<{
  nodeSelection: [nodeSelection: string]
}>()

const handleMenuClick = (item: MenuItem, event: Event) => {
  // Prevent default link behavior
  event.preventDefault()
  
  console.log('🔍 Menu item clicked:', item)
  
  // Handle internal navigation if needed
  if (item.url.startsWith('link.php') || item.url.startsWith('voter.php')) {
    // Parse node selection from URL
    const urlParams = new URLSearchParams(item.url.split('?')[1])
    const nodes = urlParams.get('nodes')
    console.log('🔍 Parsed nodes from URL:', nodes)
    if (nodes) {
      console.log('🔍 Emitting nodeSelection:', nodes)
      emit('nodeSelection', nodes)
    }
  } else if (item.url.startsWith('http')) {
    // External links - open in new tab if targetBlank is true
    if (item.targetBlank) {
      window.open(item.url, '_blank')
    } else {
      window.location.href = item.url
    }
  } else {
    // For other internal links, just emit the nodeSelection if it's a node-related URL
    // This handles cases where the URL might contain node information in other formats
    if (item.url.includes('nodes=')) {
      const urlParams = new URLSearchParams(item.url.split('?')[1])
      const nodes = urlParams.get('nodes')
      console.log('🔍 Parsed nodes from other URL:', nodes)
      if (nodes) {
        console.log('🔍 Emitting nodeSelection from other URL:', nodes)
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

// Watch for authentication changes and reload menu
watch(() => appStore.isAuthenticated, (newAuthState) => {
  console.log('🔍 Authentication state changed:', newAuthState)
  loadMenu()
})
</script>

<style scoped>
#menu {
  width: 100%;
  background-color: #000000;
  border-radius: 5px;
  margin-bottom: 20px;
  display: flex;
  justify-content: center;
  align-items: center;
  border: 1px solid #333333;
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
  color: #ecf0f1;
  text-align: center;
  padding: 8px 12px;
  text-decoration: none;
  border-radius: 5px;
  transition: background-color 0.3s ease;
  margin: 2px;
}

#menu li a:hover {
  background-color: rgba(224, 224, 224, 0.1);
}

#menu li.dropdown {
  position: relative;
  display: block;
}

#menu .dropdown-content {
  display: none;
  position: absolute;
  background-color: #000000;
  min-width: 160px;
  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
  z-index: 1;
  border-radius: 5px;
  top: 100%;
  left: 0;
}

#menu .dropdown-content a {
  color: #ecf0f1;
  padding: 12px 16px;
  text-decoration: none;
  display: block;
}

#menu .dropdown-content a:hover {
  background-color: rgba(224, 224, 224, 0.1);
}

#menu .dropdown:hover .dropdown-content {
  display: block;
}

/* Responsive Menu Styles */
@media (max-width: 768px) {
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

/* Print Menu Styles */
@media print {
  #menu {
    display: none;
  }
}
</style>
