<script setup>
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-dark-950 px-4 relative">
    <!-- Background decoration - ambient glow -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-96 h-96 bg-primary-600/15 rounded-full blur-[120px]" />
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-primary-600/10 rounded-full blur-[120px]" />
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] bg-emerald-500/5 rounded-full blur-[120px]" />
    </div>

    <div class="relative w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/[0.04] border border-white/[0.08] backdrop-blur-xl mb-4 shadow-glass">
          <img src="/logo.png" alt="KyuubiSoft" class="w-10 h-10" />
        </div>
        <h1 class="text-3xl font-bold text-gradient-accent">KyuubiSoft</h1>
        <p class="text-gray-500 mt-2 text-sm">Personal Dashboard & Tools</p>
      </div>

      <!-- Content slot - glass card -->
      <div class="glass-lg rounded-2xl p-8">
        <slot />
      </div>

      <!-- Footer -->
      <p class="text-center text-gray-600 text-xs mt-8">
        &copy; {{ new Date().getFullYear() }} KyuubiSoft. All rights reserved.
      </p>
    </div>
  </div>
</template>
