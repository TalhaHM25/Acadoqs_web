<script setup lang="ts">
interface Props {
  type?: 'success' | 'error' | 'warning' | 'info'
  message: string
  dismissible?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'info',
  dismissible: false,
})

const emit = defineEmits<{ dismiss: [] }>()

const STYLES = {
  success: 'bg-green-50 text-green-800 border-green-200',
  error:   'bg-red-50 text-red-800 border-red-200',
  warning: 'bg-amber-50 text-amber-800 border-amber-200',
  info:    'bg-blue-50 text-blue-800 border-blue-200',
}
</script>

<template>
  <div :class="['border rounded-lg px-4 py-3 flex items-start gap-3 text-sm', STYLES[type]]" role="alert">
    <span class="flex-1">{{ message }}</span>
    <button v-if="dismissible" @click="emit('dismiss')" class="ml-auto shrink-0 opacity-70 hover:opacity-100">
      &times;
    </button>
  </div>
</template>
