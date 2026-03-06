<script setup>
import DockerComposeBuilder from '@/modules/toolbox/components/tools/DockerComposeBuilder.vue'
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-white flex items-center gap-2">
        <span>🔧</span> Docker Compose Builder
      </h1>
      <p class="text-gray-400 mt-1">{{ $t('dockerModule.dockermoduleerstelledockercomposeymldateienvisuell') }}</p>
    </div>

    <div class="card p-6">
      <DockerComposeBuilder />
    </div>
  </div>
</template>
