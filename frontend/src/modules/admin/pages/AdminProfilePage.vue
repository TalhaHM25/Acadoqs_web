<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import { useAuthStore } from '@/stores/auth.store'

const auth   = useAuthStore()
const router = useRouter()
</script>

<template>
    <div class="max-w-lg mx-auto space-y-6">
      <h1 class="text-xl font-semibold text-gray-900">My Account</h1>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
        <!-- Avatar -->
        <div class="flex items-center gap-4">
          <div class="h-14 w-14 rounded-full bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shrink-0">
            <span class="text-white text-xl font-bold uppercase">
              {{ auth.user?.email?.[0] ?? 'A' }}
            </span>
          </div>
          <div>
            <p class="text-base font-semibold text-gray-900">{{ auth.user?.email }}</p>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 mt-0.5">
              Administrator
            </span>
          </div>
        </div>

        <hr class="border-gray-100" />

        <dl class="divide-y divide-gray-100 text-sm">
          <div class="flex justify-between py-2.5">
            <dt class="text-gray-500">Email</dt>
            <dd class="font-medium text-gray-900">{{ auth.user?.email }}</dd>
          </div>
          <div class="flex justify-between py-2.5">
            <dt class="text-gray-500">Role</dt>
            <dd class="font-medium text-gray-900 capitalize">{{ auth.user?.role }}</dd>
          </div>
        </dl>

        <AppButton variant="outline" :full="true" @click="router.push('/admin/change-password')">
          Change Password
        </AppButton>
      </div>
    </div>
</template>
