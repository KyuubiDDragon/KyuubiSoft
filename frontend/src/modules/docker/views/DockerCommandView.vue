<script setup>
import DockerCommandBuilder from '@/modules/toolbox/components/tools/DockerCommandBuilder.vue'
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-white flex items-center gap-2">
        <span>⚙️</span> Docker Command Builder
      </h1>
      <p class="text-gray-400 mt-1">Generiere docker run, exec und build Befehle</p>
    </div>

    <div class="card p-6">
      <DockerCommandBuilder />
    </div>
  </div>
</template>
