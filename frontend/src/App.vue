<script setup lang="ts">
import AppToast from '@/components/common/AppToast.vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import RegistrarLayout from '@/components/layout/RegistrarLayout.vue'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const layoutComponent = computed(() => {
  if (route.meta.role === 'admin' || route.path.startsWith('/admin')) return AdminLayout
  if (route.meta.role === 'registrar' || route.path.startsWith('/registrar')) return RegistrarLayout
  if (route.meta.role === 'cashier' || route.path.startsWith('/cashier')) return null
  if (route.meta.role === 'student' || route.meta.requiresAuth) return AppLayout
  return null
})
</script>

<template>
  <component :is="layoutComponent" v-if="layoutComponent">
    <router-view />
  </component>
  <router-view v-else />
  <AppToast />
</template>
