<script setup>
import DockerfileGenerator from '@/modules/toolbox/components/tools/DockerfileGenerator.vue'
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-white flex items-center gap-2">
        <span>📄</span> Dockerfile Generator
      </h1>
      <p class="text-gray-400 mt-1">Erstelle Dockerfiles für verschiedene Sprachen und Frameworks</p>
    </div>

    <div class="card p-6">
      <DockerfileGenerator />
    </div>
  </div>
</template>
