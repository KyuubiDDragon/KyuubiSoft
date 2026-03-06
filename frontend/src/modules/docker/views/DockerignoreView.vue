<script setup>
import DockerignoreGenerator from '@/modules/toolbox/components/tools/DockerignoreGenerator.vue'
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-white flex items-center gap-2">
        <span>🚫</span> .dockerignore Generator
      </h1>
      <p class="text-gray-400 mt-1">{{ $t('dockerModule.createDockerignoreFiles') }}</p>
    </div>

    <div class="card p-6">
      <DockerignoreGenerator />
    </div>
  </div>
</template>
