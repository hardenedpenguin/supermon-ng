import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from '@/views/Dashboard.vue'
import LsnodDisplay from '@/components/LsnodDisplay.vue'

const routes = [
  {
    path: '/',
    name: 'Dashboard',
    component: Dashboard
  },
  {
    path: '/lsnod/:id',
    name: 'LsnodDisplay',
    component: LsnodDisplay,
    props: true
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    redirect: '/'
  }
]

const router = createRouter({
  history: createWebHistory('/supermon-ng'),
  routes
})

export default router
