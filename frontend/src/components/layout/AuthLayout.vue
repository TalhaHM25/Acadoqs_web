<script setup lang="ts">
defineProps<{ title?: string; subtitle?: string }>()
</script>

<template>
  <div class="min-h-screen flex">

    <!-- ── Left panel: branding + quote ── -->
    <div
      class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between p-12 relative overflow-hidden"
      style="background: linear-gradient(145deg, #0ea5e9 0%, #2563eb 55%, #1d4ed8 100%);"
    >
      <!-- Soft decorative circles -->
      <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full opacity-10"
           style="background: radial-gradient(circle, #fff 0%, transparent 70%);"></div>
      <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full opacity-10"
           style="background: radial-gradient(circle, #bfdbfe 0%, transparent 70%);"></div>

      <!-- Top: logo + system name -->
      <div class="relative z-10">
        <div class="flex items-center gap-3 mb-2">
          <div class="h-11 w-11 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center shrink-0">
            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627
                   48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0
                   00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482
                   0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0
                   100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007
                   11.55A5.981 5.981 0 006.75 15.75v-1.5" />
            </svg>
          </div>
          <span class="text-white font-bold text-lg leading-tight tracking-wide">AcaDocs</span>
        </div>
      </div>

      <!-- Middle: main headline + quote -->
      <div class="relative z-10 space-y-8">
        <div>
          <h1 class="text-white font-extrabold text-3xl xl:text-4xl leading-snug">
            Kiosk and Online<br />
            Academic Document<br />
            Request System
          </h1>
          <p class="mt-3 text-sky-100 text-sm font-medium tracking-wide uppercase">
            Streamlining Academic Services
          </p>
        </div>

        <!-- Quote card -->
        <div class="bg-white/10 backdrop-blur border border-white/20 rounded-2xl p-6 space-y-3">
          <svg class="h-7 w-7 text-sky-200 opacity-80" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995
              2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017
              0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996
              3.638-3.996 5.849h3.983v10h-9.983z" />
          </svg>
          <p class="text-white/90 text-sm leading-relaxed">
            Information technology has significantly improved the efficiency of service
            delivery in educational establishments. Many offices still rely on manual
            processes for student document requests — leading to long queues, delays,
            and inefficient communication between students and administrative staff.
          </p>
          <p class="text-sky-200 text-xs font-medium">
            — AcaDocs Research Introduction
          </p>
        </div>
      </div>

      <!-- Bottom: tagline -->
      <div class="relative z-10">
        <p class="text-sky-200 text-xs">
          Serving students for TOR, COE, Certificates, Form 138 &amp; more.
        </p>
      </div>
    </div>

    <!-- ── Right panel: form ── -->
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 bg-slate-50">

      <!-- Mobile logo (shown only on small screens) -->
      <div class="lg:hidden flex items-center gap-2 mb-8">
        <div class="h-9 w-9 rounded-xl flex items-center justify-center"
             style="background: linear-gradient(135deg, #0ea5e9, #2563eb);">
          <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112
                 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482
                 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902
                 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697
                 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0
                 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112
                 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
          </svg>
        </div>
        <span class="font-bold text-gray-800 text-base">AcaDocs</span>
      </div>

      <!-- Form card -->
      <div class="w-full max-w-sm space-y-6">
        <div v-if="title || subtitle">
          <h2 v-if="title" class="text-2xl font-bold text-gray-900">{{ title }}</h2>
          <p v-if="subtitle" class="mt-1 text-sm text-gray-500">{{ subtitle }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
          <slot />
        </div>
      </div>
    </div>

  </div>
</template>
