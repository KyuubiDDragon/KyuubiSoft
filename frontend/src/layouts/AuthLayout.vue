<script setup>
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-dark-900 via-dark-800 to-dark-900 px-4">
    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-600/20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-primary-600/10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gradient">KyuubiSoft</h1>
        <p class="text-gray-400 mt-2">Personal Dashboard & Tools</p>
      </div>

      <!-- Content slot -->
      <div class="card p-8">
        <slot />
      </div>

      <!-- Footer -->
      <p class="text-center text-gray-500 text-sm mt-8">
        &copy; {{ new Date().getFullYear() }} KyuubiSoft. All rights reserved.
      </p>
    </div>
  </div>
</template>
