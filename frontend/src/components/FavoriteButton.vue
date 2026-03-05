<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFavoritesStore } from '@/stores/favorites'
import { useUiStore } from '@/stores/ui'
import { StarIcon } from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const props = defineProps({
  itemType: {
    type: String,
    required: true,
  },
  itemId: {
    type: String,
    required: true,
  },
  size: {
    type: String,
    default: 'md', // sm, md, lg
  },
  showLabel: {
    type: Boolean,
    default: false,
  },
})

const { t } = useI18n()
const favoritesStore = useFavoritesStore()
const uiStore = useUiStore()

const isFavorite = computed(() => {
  return favoritesStore.isFavorite(props.itemType, props.itemId)
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'w-4 h-4'
    case 'lg':
      return 'w-6 h-6'
    default:
      return 'w-5 h-5'
  }
})

const buttonClasses = computed(() => {
  const base = 'flex items-center gap-1.5 transition-colors'
  if (props.showLabel) {
    return `${base} px-3 py-1.5 rounded-lg ${isFavorite.value ? 'text-yellow-400 bg-yellow-500/10 hover:bg-yellow-500/20' : 'text-gray-400 hover:text-yellow-400 hover:bg-white/[0.04]'}`
  }
  return `${base} p-1.5 rounded-lg ${isFavorite.value ? 'text-yellow-400' : 'text-gray-400 hover:text-yellow-400'}`
})

async function toggleFavorite() {
  try {
    await favoritesStore.toggle(props.itemType, props.itemId)
    uiStore.showSuccess(isFavorite.value ? t('favorites.addedToFavorites') : t('favorites.removedFromFavorites'))
  } catch (error) {
    uiStore.showError(t('favorites.errorUpdating'))
  }
}
</script>

<template>
  <button
    @click.stop="toggleFavorite"
    :class="buttonClasses"
    :title="isFavorite ? $t('favorites.removeFromFavorites') : $t('favorites.addToFavorites')"
  >
    <StarIconSolid v-if="isFavorite" :class="sizeClasses" />
    <StarIcon v-else :class="sizeClasses" />
    <span v-if="showLabel" class="text-sm">
      {{ isFavorite ? $t('favorites.favorite') : $t('favorites.addFavorite') }}
    </span>
  </button>
</template>
