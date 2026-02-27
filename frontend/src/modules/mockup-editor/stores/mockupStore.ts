import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export interface MockupElement {
  id: string
  type: string
  x?: number
  y?: number
  width?: number
  height?: number
  color?: string
  gradient?: string
  src?: string
  placeholder?: string
  text?: string
  fontFamily?: string
  fontSize?: number
  fontWeight?: number
  lineHeight?: number
  letterSpacing?: string
  textTransform?: string
  textAlign?: string
  textShadow?: string
  highlightText?: string
  highlightColor?: string
  clipPath?: string | null
  objectFit?: string
  overlay?: string
  borderRadius?: number
  backgroundColor?: string
  border?: string
  boxShadow?: string
  pointerEvents?: string
  padding?: string
  transform?: string
  size?: number
  position?: string
  thickness?: number
  perspective?: string
  label?: string
  value?: string
  annotations?: MockupAnnotation[]
  [key: string]: unknown
}

export interface MockupAnnotation {
  id: string
  type?: string
  x?: number
  y?: number
  text?: string
  color?: string
  [key: string]: unknown
}

export interface MockupTemplate {
  id: string
  name: string
  description?: string
  thumbnail?: string | null
  width: number
  height: number
  aspectRatio?: string
  category?: string
  transparentBg?: boolean
  transparent_bg?: boolean
  elements: MockupElement[]
  isCustom?: boolean
  isDraft?: boolean
  [key: string]: unknown
}

export interface MockupDraft {
  id: string
  name?: string
  template_id?: string
  templateId?: string
  width: number
  height: number
  elements: MockupElement[]
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface MockupElementOptions {
  text?: string
  perspective?: string
  [key: string]: unknown
}

export const useMockupStore = defineStore('mockup', () => {
  // API state
  const customTemplates = ref<MockupTemplate[]>([])
  const drafts = ref<MockupDraft[]>([])
  const currentDraftId = ref<string | null>(null)
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  // Current mockup state
  const currentTemplate = ref<MockupTemplate | null>(null)
  const elements = ref<MockupElement[]>([])
  const selectedElementId = ref<string | null>(null)
  const canvasWidth = ref<number>(1920)
  const canvasHeight = ref<number>(1080)
  const backgroundColor = ref<string>('transparent')
  const zoom = ref<number>(1)

  // Template presets for Tebex store
  const templates = ref<MockupTemplate[]>([
    {
      id: 'single-image-hero',
      name: 'Single Image Hero',
      description: 'Ein Bild mit schräger Kante und Textbereich',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'hero',
      elements: [
        {
          id: 'bg',
          type: 'background',
          color: '#0d0d0f',
          gradient: 'radial-gradient(1200px 700px at 50% 20%, #131316, #0d0d0f 60%)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)',
          clipPath: null
        },
        {
          id: 'hero-image',
          type: 'image',
          x: 0,
          y: 0,
          width: 1152,
          height: 1080,
          src: '',
          placeholder: 'Bild hier ablegen oder klicken',
          clipPath: 'polygon(0 0, calc(100% - 10%) 0, 100% 100%, 0 100%)',
          objectFit: 'cover',
          overlay: 'linear-gradient(90deg, rgba(13,13,15,0.85) 0%, rgba(13,13,15,0.25) 35%, rgba(13,13,15,0) 60%)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 1150,
          y: 400,
          width: 650,
          text: 'KYUUBISOFT TABX',
          fontFamily: 'Outfit',
          fontSize: 52,
          fontWeight: 800,
          color: '#f0f0f2',
          letterSpacing: '-0.02em',
          textTransform: 'uppercase',
          lineHeight: 1.05,
          highlightText: 'TABX',
          highlightColor: '#f4b400'
        },
        {
          id: 'description',
          type: 'text',
          x: 1150,
          y: 480,
          width: 450,
          text: 'Ein Screenshot. Volle Wirkung. Premium Präsentation direkt im Store.',
          fontFamily: 'DM Sans',
          fontSize: 18,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.5
        },
        {
          id: 'divider',
          type: 'line',
          x: 1150,
          y: 560,
          width: 80,
          height: 2,
          gradient: 'linear-gradient(90deg, #f4b400, transparent)'
        }
      ]
    },
    {
      id: 'feature-showcase',
      name: 'Feature Showcase',
      description: 'Mehrere Screenshots mit Feature-Highlights',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'showcase',
      elements: [
        {
          id: 'bg',
          type: 'background',
          color: '#0d0d0f',
          gradient: 'radial-gradient(900px 520px at 30% 40%, rgba(244,180,0,0.10), transparent 55%), linear-gradient(135deg, #0d0d0f 0%, #1a1a1f 100%)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'main-image',
          type: 'image',
          x: 30,
          y: 70,
          width: 1150,
          height: 780,
          src: '',
          placeholder: 'Haupt-Screenshot',
          borderRadius: 12,
          boxShadow: '0 20px 60px rgba(0,0,0,0.5)'
        },
        {
          id: 'feature-1',
          type: 'image',
          x: 1210,
          y: 30,
          width: 680,
          height: 320,
          src: '',
          placeholder: 'Feature 1',
          borderRadius: 8,
          boxShadow: '0 10px 40px rgba(0,0,0,0.4)'
        },
        {
          id: 'feature-2',
          type: 'image',
          x: 1210,
          y: 370,
          width: 680,
          height: 320,
          src: '',
          placeholder: 'Feature 2',
          borderRadius: 8,
          boxShadow: '0 10px 40px rgba(0,0,0,0.4)'
        },
        {
          id: 'feature-3',
          type: 'image',
          x: 1210,
          y: 710,
          width: 680,
          height: 320,
          src: '',
          placeholder: 'Feature 3',
          borderRadius: 8,
          boxShadow: '0 10px 40px rgba(0,0,0,0.4)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 30,
          y: 880,
          width: 800,
          text: 'PRODUKTNAME',
          fontFamily: 'Outfit',
          fontSize: 48,
          fontWeight: 800,
          color: '#f0f0f2',
          textTransform: 'uppercase'
        },
        {
          id: 'gold-accent',
          type: 'line',
          x: 30,
          y: 950,
          width: 120,
          height: 4,
          color: '#f4b400'
        }
      ]
    },
    {
      id: 'corner-frame',
      name: 'Corner Frame',
      description: 'Eleganter Rahmen mit Ecken (transparenter Hintergrund)',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'frame',
      transparentBg: true,
      elements: [
        {
          id: 'bg',
          type: 'background',
          color: 'transparent'
        },
        {
          id: 'corner-tl',
          type: 'corner',
          x: 40,
          y: 40,
          size: 60,
          color: '#f4b400',
          position: 'top-left',
          thickness: 3
        },
        {
          id: 'corner-tr',
          type: 'corner',
          x: 1820,
          y: 40,
          size: 60,
          color: '#f4b400',
          position: 'top-right',
          thickness: 3
        },
        {
          id: 'corner-bl',
          type: 'corner',
          x: 40,
          y: 980,
          size: 60,
          color: '#f4b400',
          position: 'bottom-left',
          thickness: 3
        },
        {
          id: 'corner-br',
          type: 'corner',
          x: 1820,
          y: 980,
          size: 60,
          color: '#f4b400',
          position: 'bottom-right',
          thickness: 3
        },
        {
          id: 'main-image',
          type: 'image',
          x: 100,
          y: 100,
          width: 1720,
          height: 880,
          src: '',
          placeholder: 'Bild hier ablegen',
          objectFit: 'cover'
        },
        {
          id: 'title',
          type: 'text',
          x: 960,
          y: 520,
          width: 800,
          text: '',
          fontFamily: 'Outfit',
          fontSize: 64,
          fontWeight: 800,
          color: '#ffffff',
          textAlign: 'center',
          textShadow: '0 4px 20px rgba(0,0,0,0.8)'
        }
      ]
    },
    {
      id: 'price-banner',
      name: 'Preis-Banner',
      description: 'Banner mit Preis und Call-to-Action',
      thumbnail: null,
      width: 1920,
      height: 400,
      aspectRatio: '48:10',
      category: 'banner',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(600px 300px at 50% 50%, rgba(244,180,0,0.08), transparent 55%), linear-gradient(90deg, #0d0d0f 0%, #1a1a2e 50%, #0d0d0f 100%)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 400,
          borderRadius: 14,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 16px 60px rgba(0,0,0,0.55)'
        },
        {
          id: 'product-image',
          type: 'image',
          x: 20,
          y: 15,
          width: 370,
          height: 370,
          src: '',
          placeholder: 'Produkt',
          borderRadius: 12
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 380,
          borderRadius: 10,
          border: '1px solid rgba(244,180,0,0.15)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 420,
          y: 100,
          width: 800,
          text: 'PRODUKTNAME',
          fontFamily: 'Outfit',
          fontSize: 52,
          fontWeight: 800,
          color: '#f0f0f2',
          textTransform: 'uppercase'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 420,
          y: 170,
          width: 600,
          text: 'Kurze Beschreibung deines Produkts',
          fontFamily: 'DM Sans',
          fontSize: 20,
          color: '#8b8b93'
        },
        {
          id: 'price',
          type: 'text',
          x: 1450,
          y: 100,
          width: 350,
          text: '€19.99',
          fontFamily: 'Outfit',
          fontSize: 64,
          fontWeight: 800,
          color: '#f4b400',
          textAlign: 'right'
        },
        {
          id: 'cta',
          type: 'button',
          x: 1550,
          y: 190,
          width: 300,
          height: 60,
          text: 'JETZT KAUFEN',
          fontFamily: 'Outfit',
          fontSize: 18,
          fontWeight: 700,
          color: '#0d0d0f',
          backgroundColor: '#f4b400',
          borderRadius: 8
        }
      ]
    },
    {
      id: 'minimal-dark',
      name: 'Minimal Dark',
      description: 'Minimalistisches dunkles Design',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'minimal',
      elements: [
        {
          id: 'bg',
          type: 'background',
          color: '#0a0a0a',
          gradient: 'radial-gradient(800px 500px at 50% 50%, rgba(244,180,0,0.06), transparent 60%), linear-gradient(180deg, #0a0a0a, #0d0d0f)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#111113',
          border: '1px solid rgba(255,255,255,0.06)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'main-image',
          type: 'image',
          x: 110,
          y: 130,
          width: 1700,
          height: 820,
          src: '',
          placeholder: 'Screenshot',
          borderRadius: 12,
          boxShadow: '0 0 80px rgba(244, 180, 0, 0.12)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.12)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'logo',
          type: 'image',
          x: 860,
          y: 35,
          width: 200,
          height: 60,
          src: '',
          placeholder: 'Logo',
          objectFit: 'contain'
        },
        {
          id: 'bottom-text',
          type: 'text',
          x: 960,
          y: 1000,
          width: 800,
          text: 'kyuubisoft.com',
          fontFamily: 'DM Sans',
          fontSize: 16,
          fontWeight: 500,
          color: '#4a4a4a',
          textAlign: 'center'
        }
      ]
    },
    {
      id: 'single-image-hero-right',
      name: 'Single Image Hero (Rechts)',
      description: 'Ein Bild rechts mit schräger Kante und Textbereich links',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'hero',
      elements: [
        {
          id: 'bg',
          type: 'background',
          color: '#0d0d0f',
          gradient: 'radial-gradient(1200px 700px at 50% 20%, #131316, #0d0d0f 60%)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'hero-image',
          type: 'image',
          x: 768,
          y: 0,
          width: 1152,
          height: 1080,
          src: '',
          placeholder: 'Bild hier ablegen oder klicken',
          clipPath: 'polygon(10% 0, 100% 0, 100% 100%, 0 100%)',
          objectFit: 'cover',
          overlay: 'linear-gradient(270deg, rgba(13,13,15,0.85) 0%, rgba(13,13,15,0.25) 35%, rgba(13,13,15,0) 60%)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 50,
          y: 400,
          width: 650,
          text: 'KYUUBISOFT TABX',
          fontFamily: 'Outfit',
          fontSize: 52,
          fontWeight: 800,
          color: '#f0f0f2',
          letterSpacing: '-0.02em',
          textTransform: 'uppercase',
          lineHeight: 1.05,
          highlightText: 'TABX',
          highlightColor: '#f4b400'
        },
        {
          id: 'description',
          type: 'text',
          x: 50,
          y: 480,
          width: 450,
          text: 'Ein Screenshot. Volle Wirkung. Premium Präsentation direkt im Store.',
          fontFamily: 'DM Sans',
          fontSize: 18,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.5
        },
        {
          id: 'divider',
          type: 'line',
          x: 50,
          y: 560,
          width: 80,
          height: 2,
          gradient: 'linear-gradient(90deg, #f4b400, transparent)'
        }
      ]
    },
    {
      id: 'product-card-premium',
      name: 'Product Card Premium',
      description: 'Komplette Produktkarte mit Features, Stats und 3 Screenshots',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'showcase',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(900px 520px at 50% 30%, rgba(244,180,0,0.12), transparent 55%), radial-gradient(800px 500px at 10% 80%, rgba(255,255,255,0.05), transparent 60%), linear-gradient(180deg, #0d0d0f, #141416 38%, #0d0d0f)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'kicker',
          type: 'chip',
          x: 860,
          y: 30,
          text: '● TabX Store • Premium Script',
          fontFamily: 'DM Sans',
          fontSize: 12,
          color: '#9898a3',
          backgroundColor: 'rgba(28,28,31,0.72)',
          border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: 10,
          padding: '7px 12px'
        },
        {
          id: 'title',
          type: 'text',
          x: 560,
          y: 70,
          width: 800,
          text: 'KyuubiSoft Produktname',
          fontFamily: 'Outfit',
          fontSize: 52,
          fontWeight: 700,
          color: '#f0f0f2',
          textAlign: 'center',
          highlightText: 'Soft',
          highlightColor: '#f4b400'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 610,
          y: 135,
          width: 700,
          text: 'Modular, optimiert und easy zu integrieren.',
          fontFamily: 'DM Sans',
          fontSize: 18,
          fontWeight: 400,
          color: '#606068',
          textAlign: 'center'
        },
        {
          id: 'chip-1',
          type: 'chip',
          x: 580,
          y: 180,
          text: '⚡ Optimized Performance',
          fontFamily: 'DM Sans',
          fontSize: 12,
          color: '#9898a3',
          backgroundColor: 'rgba(28,28,31,0.72)',
          border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: 10
        },
        {
          id: 'chip-2',
          type: 'chip',
          x: 780,
          y: 180,
          text: '🔧 Modular Setup',
          fontFamily: 'DM Sans',
          fontSize: 12,
          color: '#9898a3',
          backgroundColor: 'rgba(28,28,31,0.72)',
          border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: 10
        },
        {
          id: 'chip-3',
          type: 'chip',
          x: 950,
          y: 180,
          text: '🛡️ Support inklusive',
          fontFamily: 'DM Sans',
          fontSize: 12,
          color: '#9898a3',
          backgroundColor: 'rgba(28,28,31,0.72)',
          border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: 10
        },
        {
          id: 'chip-4',
          type: 'chip',
          x: 1140,
          y: 180,
          text: '🧩 Ready to use',
          fontFamily: 'DM Sans',
          fontSize: 12,
          color: '#9898a3',
          backgroundColor: 'rgba(28,28,31,0.72)',
          border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: 10
        },
        {
          id: 'stat-1',
          type: 'stat',
          x: 570,
          y: 240,
          width: 250,
          label: 'Version',
          value: 'v2.0.0 Stable',
          highlightText: 'v2.0.0',
          highlightColor: '#f4b400'
        },
        {
          id: 'stat-2',
          type: 'stat',
          x: 835,
          y: 240,
          width: 250,
          label: 'Kompatibilität',
          value: 'FiveM • QBCore'
        },
        {
          id: 'stat-3',
          type: 'stat',
          x: 1100,
          y: 240,
          width: 250,
          label: 'Update-Status',
          value: 'Letztes Update: Jan 2026',
          highlightText: 'Jan 2026',
          highlightColor: '#f4b400'
        },
        {
          id: 'screen-left',
          type: 'screen3d',
          x: 70,
          y: 370,
          width: 520,
          height: 600,
          src: '',
          placeholder: 'Screenshot links',
          perspective: 'left',
          borderRadius: 12
        },
        {
          id: 'screen-center',
          type: 'screen3d',
          x: 610,
          y: 330,
          width: 700,
          height: 700,
          src: '',
          placeholder: 'Screenshot Mitte',
          perspective: 'center',
          borderRadius: 12
        },
        {
          id: 'screen-right',
          type: 'screen3d',
          x: 1330,
          y: 370,
          width: 520,
          height: 600,
          src: '',
          placeholder: 'Screenshot rechts',
          perspective: 'right',
          borderRadius: 12
        }
      ]
    },
    {
      id: 'wide-screens-banner',
      name: 'Wide Screens Banner',
      description: 'Titel oben mit 3 Screenshots in 3D-Perspektive',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'showcase',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(900px 520px at 65% 28%, rgba(244,180,0,0.16), transparent 58%), radial-gradient(700px 420px at 12% 72%, rgba(255,255,255,0.06), transparent 60%), linear-gradient(180deg, #0d0d0f, #141416 45%, #0d0d0f)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'bg-stripe',
          type: 'line',
          x: -250,
          y: 150,
          width: 2400,
          height: 300,
          gradient: 'linear-gradient(90deg, rgba(244,180,0,0), rgba(244,180,0,0.14), rgba(244,180,0,0))',
          transform: 'rotate(-8deg)'
        },
        {
          id: 'title',
          type: 'text',
          x: 960,
          y: 70,
          width: 1200,
          text: 'KYUUBISOFT TABX SCRIPT',
          fontFamily: 'Outfit',
          fontSize: 66,
          fontWeight: 800,
          color: '#f0f0f2',
          textAlign: 'center',
          textTransform: 'uppercase',
          letterSpacing: '-0.02em',
          highlightText: 'SOFT',
          highlightColor: '#f4b400'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 960,
          y: 150,
          width: 800,
          text: 'Plug and Play. Premium Look. Saubere Performance.',
          fontFamily: 'DM Sans',
          fontSize: 20,
          fontWeight: 400,
          color: '#606068',
          textAlign: 'center'
        },
        {
          id: 'underline',
          type: 'line',
          x: 700,
          y: 200,
          width: 520,
          height: 1,
          gradient: 'linear-gradient(90deg, transparent, rgba(244,180,0,0.55), transparent)'
        },
        {
          id: 'screen-left',
          type: 'screen3d',
          x: 50,
          y: 330,
          width: 500,
          height: 550,
          src: '',
          placeholder: 'Screenshot links',
          perspective: 'left',
          borderRadius: 12
        },
        {
          id: 'screen-center',
          type: 'screen3d',
          x: 610,
          y: 290,
          width: 700,
          height: 700,
          src: '',
          placeholder: 'Screenshot Mitte',
          perspective: 'center',
          borderRadius: 12
        },
        {
          id: 'screen-right',
          type: 'screen3d',
          x: 1370,
          y: 330,
          width: 500,
          height: 550,
          src: '',
          placeholder: 'Screenshot rechts',
          perspective: 'right',
          borderRadius: 12
        }
      ]
    },
    {
      id: 'comparison-split',
      name: 'Comparison Split',
      description: 'Vorher/Nachher Vergleich mit zwei Screenshots',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'comparison',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(900px 520px at 50% 50%, rgba(244,180,0,0.10), transparent 55%), linear-gradient(135deg, #0d0d0f 0%, #141416 100%)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 960,
          y: 50,
          width: 1000,
          text: 'VORHER VS NACHHER',
          fontFamily: 'Outfit',
          fontSize: 48,
          fontWeight: 800,
          color: '#f0f0f2',
          textAlign: 'center',
          textTransform: 'uppercase',
          highlightText: 'VS',
          highlightColor: '#f4b400'
        },
        {
          id: 'left-label',
          type: 'chip',
          x: 420,
          y: 130,
          text: 'VORHER',
          fontFamily: 'Outfit',
          fontSize: 14,
          fontWeight: 700,
          color: '#ff6b6b',
          backgroundColor: 'rgba(255,107,107,0.15)',
          border: '1px solid rgba(255,107,107,0.3)',
          borderRadius: 8
        },
        {
          id: 'right-label',
          type: 'chip',
          x: 1420,
          y: 130,
          text: 'NACHHER',
          fontFamily: 'Outfit',
          fontSize: 14,
          fontWeight: 700,
          color: '#51cf66',
          backgroundColor: 'rgba(81,207,102,0.15)',
          border: '1px solid rgba(81,207,102,0.3)',
          borderRadius: 8
        },
        {
          id: 'left-image',
          type: 'image',
          x: 50,
          y: 180,
          width: 880,
          height: 700,
          src: '',
          placeholder: 'Vorher Screenshot',
          borderRadius: 12,
          boxShadow: '0 20px 60px rgba(0,0,0,0.5)'
        },
        {
          id: 'right-image',
          type: 'image',
          x: 990,
          y: 180,
          width: 880,
          height: 700,
          src: '',
          placeholder: 'Nachher Screenshot',
          borderRadius: 12,
          boxShadow: '0 20px 60px rgba(0,0,0,0.5)'
        },
        {
          id: 'divider-line',
          type: 'line',
          x: 955,
          y: 180,
          width: 4,
          height: 700,
          gradient: 'linear-gradient(180deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'vs-badge',
          type: 'chip',
          x: 920,
          y: 500,
          text: 'VS',
          fontFamily: 'Outfit',
          fontSize: 20,
          fontWeight: 800,
          color: '#0d0d0f',
          backgroundColor: '#f4b400',
          borderRadius: 50,
          padding: '12px 20px'
        },
        {
          id: 'bottom-text',
          type: 'text',
          x: 960,
          y: 920,
          width: 800,
          text: 'Upgrade jetzt auf die neue Version!',
          fontFamily: 'DM Sans',
          fontSize: 18,
          color: '#606068',
          textAlign: 'center'
        }
      ]
    },
    {
      id: 'features-grid',
      name: 'Features Grid',
      description: '6 Features mit Icons in Grid-Layout',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'showcase',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(800px 500px at 50% 30%, rgba(244,180,0,0.12), transparent 55%), linear-gradient(180deg, #0d0d0f, #141416 50%, #0d0d0f)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 960,
          y: 60,
          width: 1200,
          text: 'WARUM KYUUBISOFT?',
          fontFamily: 'Outfit',
          fontSize: 56,
          fontWeight: 800,
          color: '#f0f0f2',
          textAlign: 'center',
          textTransform: 'uppercase',
          highlightText: 'KYUUBISOFT',
          highlightColor: '#f4b400'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 960,
          y: 140,
          width: 800,
          text: 'Premium Features für dein FiveM Projekt',
          fontFamily: 'DM Sans',
          fontSize: 20,
          color: '#606068',
          textAlign: 'center'
        },
        {
          id: 'feature-1',
          type: 'stat',
          x: 120,
          y: 220,
          width: 540,
          label: '⚡ Performance',
          value: 'Optimiert für 0.00ms Resmon'
        },
        {
          id: 'feature-2',
          type: 'stat',
          x: 690,
          y: 220,
          width: 540,
          label: '🔧 Modular',
          value: 'Einfache Konfiguration'
        },
        {
          id: 'feature-3',
          type: 'stat',
          x: 1260,
          y: 220,
          width: 540,
          label: '🛡️ Support',
          value: '24/7 Discord Support'
        },
        {
          id: 'feature-4',
          type: 'stat',
          x: 120,
          y: 320,
          width: 540,
          label: '🔄 Updates',
          value: 'Regelmäßige Updates'
        },
        {
          id: 'feature-5',
          type: 'stat',
          x: 690,
          y: 320,
          width: 540,
          label: '📚 Dokumentation',
          value: 'Ausführliche Docs'
        },
        {
          id: 'feature-6',
          type: 'stat',
          x: 1260,
          y: 320,
          width: 540,
          label: '🎨 Anpassbar',
          value: 'Vollständig customizable'
        },
        {
          id: 'main-screenshot',
          type: 'image',
          x: 260,
          y: 440,
          width: 1400,
          height: 580,
          src: '',
          placeholder: 'Haupt-Screenshot',
          borderRadius: 16,
          boxShadow: '0 30px 80px rgba(0,0,0,0.6)'
        }
      ]
    },
    {
      id: 'update-banner',
      name: 'Update Banner',
      description: 'Banner für neue Versionen und Updates',
      thumbnail: null,
      width: 1920,
      height: 600,
      aspectRatio: '16:5',
      category: 'banner',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(800px 400px at 70% 50%, rgba(244,180,0,0.15), transparent 55%), linear-gradient(90deg, #0d0d0f 0%, #141416 50%, #0d0d0f 100%)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 600,
          borderRadius: 16,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 20px 60px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 580,
          borderRadius: 12,
          border: '1px solid rgba(244,180,0,0.15)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'version-badge',
          type: 'chip',
          x: 100,
          y: 80,
          text: '🎉 NEW UPDATE',
          fontFamily: 'Outfit',
          fontSize: 14,
          fontWeight: 700,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.15)',
          border: '1px solid rgba(244,180,0,0.3)',
          borderRadius: 8
        },
        {
          id: 'title',
          type: 'text',
          x: 100,
          y: 130,
          width: 800,
          text: 'VERSION 2.0 IST DA!',
          fontFamily: 'Outfit',
          fontSize: 64,
          fontWeight: 800,
          color: '#f0f0f2',
          textTransform: 'uppercase',
          highlightText: '2.0',
          highlightColor: '#f4b400'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 100,
          y: 220,
          width: 700,
          text: 'Komplett überarbeitet mit neuen Features, besserem Performance und mehr Optionen.',
          fontFamily: 'DM Sans',
          fontSize: 20,
          color: '#8b8b93',
          lineHeight: 1.5
        },
        {
          id: 'feature-list',
          type: 'text',
          x: 100,
          y: 320,
          width: 600,
          text: '✓ Neues UI Design\n✓ 50% schneller\n✓ Mehr Konfiguration\n✓ Bug Fixes',
          fontFamily: 'DM Sans',
          fontSize: 16,
          color: '#51cf66',
          lineHeight: 2
        },
        {
          id: 'screenshot',
          type: 'screen3d',
          x: 1100,
          y: 50,
          width: 750,
          height: 500,
          src: '',
          placeholder: 'Update Screenshot',
          perspective: 'right',
          borderRadius: 12
        }
      ]
    },
    {
      id: 'gallery-strip',
      name: 'Gallery Strip',
      description: '4 Screenshots in einer Reihe',
      thumbnail: null,
      width: 1920,
      height: 700,
      aspectRatio: '24:7',
      category: 'gallery',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'linear-gradient(180deg, #0d0d0f, #141416 50%, #0d0d0f)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 700,
          borderRadius: 16,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 20px 60px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 680,
          borderRadius: 12,
          border: '1px solid rgba(244,180,0,0.15)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 960,
          y: 40,
          width: 1000,
          text: 'SCREENSHOT GALERIE',
          fontFamily: 'Outfit',
          fontSize: 36,
          fontWeight: 800,
          color: '#f0f0f2',
          textAlign: 'center',
          textTransform: 'uppercase',
          highlightText: 'GALERIE',
          highlightColor: '#f4b400'
        },
        {
          id: 'screen-1',
          type: 'image',
          x: 40,
          y: 120,
          width: 450,
          height: 540,
          src: '',
          placeholder: 'Screenshot 1',
          borderRadius: 12,
          boxShadow: '0 15px 40px rgba(0,0,0,0.4)'
        },
        {
          id: 'screen-2',
          type: 'image',
          x: 510,
          y: 120,
          width: 450,
          height: 540,
          src: '',
          placeholder: 'Screenshot 2',
          borderRadius: 12,
          boxShadow: '0 15px 40px rgba(0,0,0,0.4)'
        },
        {
          id: 'screen-3',
          type: 'image',
          x: 980,
          y: 120,
          width: 450,
          height: 540,
          src: '',
          placeholder: 'Screenshot 3',
          borderRadius: 12,
          boxShadow: '0 15px 40px rgba(0,0,0,0.4)'
        },
        {
          id: 'screen-4',
          type: 'image',
          x: 1450,
          y: 120,
          width: 450,
          height: 540,
          src: '',
          placeholder: 'Screenshot 4',
          borderRadius: 12,
          boxShadow: '0 15px 40px rgba(0,0,0,0.4)'
        }
      ]
    },
    {
      id: 'discord-banner',
      name: 'Discord Server Banner',
      description: 'Banner für Discord Server (960x540)',
      thumbnail: null,
      width: 960,
      height: 540,
      aspectRatio: '16:9',
      category: 'social',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(600px 400px at 50% 40%, rgba(244,180,0,0.18), transparent 55%), linear-gradient(135deg, #0d0d0f, #1a1a2e)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 960,
          height: 540,
          borderRadius: 0,
          backgroundColor: '#141416',
          border: 'none',
          boxShadow: 'none'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 0,
          y: 0,
          width: 960,
          height: 3,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'logo-image',
          type: 'image',
          x: 380,
          y: 80,
          width: 200,
          height: 200,
          src: '',
          placeholder: 'Logo',
          objectFit: 'contain'
        },
        {
          id: 'title',
          type: 'text',
          x: 480,
          y: 300,
          width: 800,
          text: 'KYUUBISOFT',
          fontFamily: 'Outfit',
          fontSize: 56,
          fontWeight: 800,
          color: '#f0f0f2',
          textAlign: 'center',
          textTransform: 'uppercase',
          highlightText: 'SOFT',
          highlightColor: '#f4b400'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 480,
          y: 370,
          width: 600,
          text: 'Premium FiveM Scripts',
          fontFamily: 'DM Sans',
          fontSize: 22,
          color: '#8b8b93',
          textAlign: 'center'
        },
        {
          id: 'discord-tag',
          type: 'chip',
          x: 400,
          y: 440,
          text: '🎮 discord.gg/kyuubisoft',
          fontFamily: 'DM Sans',
          fontSize: 14,
          color: '#7289da',
          backgroundColor: 'rgba(114,137,218,0.15)',
          border: '1px solid rgba(114,137,218,0.3)',
          borderRadius: 8
        }
      ]
    },
    {
      id: 'feature-gallery-3d',
      name: 'Feature Gallery 3D',
      description: '4 Screenshots mit 3D-Effekt und Feature-Beschreibungen',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'showcase',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(1000px 600px at 50% 35%, rgba(244,180,0,0.14), transparent 55%), radial-gradient(600px 400px at 15% 75%, rgba(255,255,255,0.04), transparent 50%), linear-gradient(180deg, #0d0d0f, #141416 40%, #0d0d0f)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 960,
          y: 50,
          width: 1200,
          text: 'FEATURE ÜBERSICHT',
          fontFamily: 'Outfit',
          fontSize: 54,
          fontWeight: 800,
          color: '#f0f0f2',
          textAlign: 'center',
          textTransform: 'uppercase',
          letterSpacing: '-0.02em',
          highlightText: 'FEATURE',
          highlightColor: '#f4b400'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 960,
          y: 120,
          width: 800,
          text: 'Alle wichtigen Funktionen auf einen Blick',
          fontFamily: 'DM Sans',
          fontSize: 20,
          fontWeight: 400,
          color: '#606068',
          textAlign: 'center'
        },
        // Feature 1 - Top Left
        {
          id: 'screen-1',
          type: 'screen3d',
          x: 60,
          y: 200,
          width: 420,
          height: 320,
          src: '',
          placeholder: 'Feature 1',
          perspective: 'left',
          borderRadius: 12
        },
        {
          id: 'feature-1-badge',
          type: 'chip',
          x: 110,
          y: 540,
          text: '01',
          fontFamily: 'Outfit',
          fontSize: 12,
          fontWeight: 700,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.15)',
          border: '1px solid rgba(244,180,0,0.3)',
          borderRadius: 6
        },
        {
          id: 'feature-1-title',
          type: 'text',
          x: 60,
          y: 570,
          width: 420,
          text: 'Dashboard',
          fontFamily: 'Outfit',
          fontSize: 24,
          fontWeight: 700,
          color: '#f0f0f2'
        },
        {
          id: 'feature-1-desc',
          type: 'text',
          x: 60,
          y: 605,
          width: 420,
          text: 'Übersichtliches Dashboard mit allen wichtigen Statistiken und Daten.',
          fontFamily: 'DM Sans',
          fontSize: 14,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.5
        },
        // Feature 2 - Top Right
        {
          id: 'screen-2',
          type: 'screen3d',
          x: 530,
          y: 200,
          width: 420,
          height: 320,
          src: '',
          placeholder: 'Feature 2',
          perspective: 'center',
          borderRadius: 12
        },
        {
          id: 'feature-2-badge',
          type: 'chip',
          x: 580,
          y: 540,
          text: '02',
          fontFamily: 'Outfit',
          fontSize: 12,
          fontWeight: 700,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.15)',
          border: '1px solid rgba(244,180,0,0.3)',
          borderRadius: 6
        },
        {
          id: 'feature-2-title',
          type: 'text',
          x: 530,
          y: 570,
          width: 420,
          text: 'Verwaltung',
          fontFamily: 'Outfit',
          fontSize: 24,
          fontWeight: 700,
          color: '#f0f0f2'
        },
        {
          id: 'feature-2-desc',
          type: 'text',
          x: 530,
          y: 605,
          width: 420,
          text: 'Einfache Verwaltung aller Einträge mit Such- und Filterfunktionen.',
          fontFamily: 'DM Sans',
          fontSize: 14,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.5
        },
        // Feature 3 - Bottom Left
        {
          id: 'screen-3',
          type: 'screen3d',
          x: 1000,
          y: 200,
          width: 420,
          height: 320,
          src: '',
          placeholder: 'Feature 3',
          perspective: 'center',
          borderRadius: 12
        },
        {
          id: 'feature-3-badge',
          type: 'chip',
          x: 1050,
          y: 540,
          text: '03',
          fontFamily: 'Outfit',
          fontSize: 12,
          fontWeight: 700,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.15)',
          border: '1px solid rgba(244,180,0,0.3)',
          borderRadius: 6
        },
        {
          id: 'feature-3-title',
          type: 'text',
          x: 1000,
          y: 570,
          width: 420,
          text: 'Einstellungen',
          fontFamily: 'Outfit',
          fontSize: 24,
          fontWeight: 700,
          color: '#f0f0f2'
        },
        {
          id: 'feature-3-desc',
          type: 'text',
          x: 1000,
          y: 605,
          width: 420,
          text: 'Umfangreiche Konfiguration über eine intuitive Config-Datei.',
          fontFamily: 'DM Sans',
          fontSize: 14,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.5
        },
        // Feature 4 - Bottom Right
        {
          id: 'screen-4',
          type: 'screen3d',
          x: 1470,
          y: 200,
          width: 420,
          height: 320,
          src: '',
          placeholder: 'Feature 4',
          perspective: 'right',
          borderRadius: 12
        },
        {
          id: 'feature-4-badge',
          type: 'chip',
          x: 1520,
          y: 540,
          text: '04',
          fontFamily: 'Outfit',
          fontSize: 12,
          fontWeight: 700,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.15)',
          border: '1px solid rgba(244,180,0,0.3)',
          borderRadius: 6
        },
        {
          id: 'feature-4-title',
          type: 'text',
          x: 1470,
          y: 570,
          width: 420,
          text: 'Benachrichtigungen',
          fontFamily: 'Outfit',
          fontSize: 24,
          fontWeight: 700,
          color: '#f0f0f2'
        },
        {
          id: 'feature-4-desc',
          type: 'text',
          x: 1470,
          y: 605,
          width: 420,
          text: 'Individuelle Notifications für alle wichtigen Events.',
          fontFamily: 'DM Sans',
          fontSize: 14,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.5
        },
        // Bottom CTA Section
        {
          id: 'bottom-divider',
          type: 'line',
          x: 700,
          y: 700,
          width: 520,
          height: 1,
          gradient: 'linear-gradient(90deg, transparent, rgba(244,180,0,0.4), transparent)'
        },
        {
          id: 'bottom-text',
          type: 'text',
          x: 960,
          y: 740,
          width: 800,
          text: 'Und vieles mehr...',
          fontFamily: 'DM Sans',
          fontSize: 18,
          fontWeight: 500,
          color: '#606068',
          textAlign: 'center'
        },
        // Main Highlight Screenshot
        {
          id: 'main-screen',
          type: 'screen3d',
          x: 560,
          y: 780,
          width: 800,
          height: 260,
          src: '',
          placeholder: 'Haupt-Screenshot',
          perspective: 'center',
          borderRadius: 14
        }
      ]
    },
    {
      id: 'feature-cards-premium',
      name: 'Feature Cards Premium',
      description: '3 Feature-Karten mit 3D Screenshots und Beschreibungen',
      thumbnail: null,
      width: 1920,
      height: 1080,
      aspectRatio: '16:9',
      category: 'showcase',
      elements: [
        {
          id: 'bg',
          type: 'background',
          gradient: 'radial-gradient(900px 550px at 50% 60%, rgba(244,180,0,0.12), transparent 55%), radial-gradient(700px 400px at 80% 20%, rgba(255,255,255,0.05), transparent 50%), linear-gradient(180deg, #0d0d0f, #141416 50%, #0d0d0f)'
        },
        {
          id: 'hero-container',
          type: 'container',
          x: 0,
          y: 0,
          width: 1920,
          height: 1080,
          borderRadius: 18,
          backgroundColor: '#141416',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 24px 80px rgba(0,0,0,0.55)'
        },
        {
          id: 'gold-line',
          type: 'line',
          x: 28,
          y: 0,
          width: 1864,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)'
        },
        {
          id: 'inner-frame',
          type: 'container',
          x: 10,
          y: 10,
          width: 1900,
          height: 1060,
          borderRadius: 14,
          border: '1px solid rgba(244,180,0,0.18)',
          backgroundColor: 'transparent',
          pointerEvents: 'none'
        },
        {
          id: 'title',
          type: 'text',
          x: 960,
          y: 50,
          width: 1200,
          text: 'DAS KANN UNSER SCRIPT',
          fontFamily: 'Outfit',
          fontSize: 48,
          fontWeight: 800,
          color: '#f0f0f2',
          textAlign: 'center',
          textTransform: 'uppercase',
          letterSpacing: '-0.02em',
          highlightText: 'SCRIPT',
          highlightColor: '#f4b400'
        },
        {
          id: 'subtitle',
          type: 'text',
          x: 960,
          y: 115,
          width: 800,
          text: 'Premium Features für maximale Performance',
          fontFamily: 'DM Sans',
          fontSize: 20,
          fontWeight: 400,
          color: '#606068',
          textAlign: 'center'
        },
        // Card 1
        {
          id: 'card-1-bg',
          type: 'container',
          x: 60,
          y: 180,
          width: 580,
          height: 820,
          borderRadius: 16,
          backgroundColor: 'rgba(20,20,22,0.6)',
          border: '1px solid rgba(255,255,255,0.06)',
          boxShadow: '0 20px 60px rgba(0,0,0,0.4)'
        },
        {
          id: 'screen-1',
          type: 'screen3d',
          x: 90,
          y: 210,
          width: 520,
          height: 400,
          src: '',
          placeholder: 'Feature Screenshot 1',
          perspective: 'left',
          borderRadius: 12
        },
        {
          id: 'card-1-icon',
          type: 'chip',
          x: 90,
          y: 640,
          text: '⚡',
          fontFamily: 'DM Sans',
          fontSize: 20,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.12)',
          border: '1px solid rgba(244,180,0,0.25)',
          borderRadius: 10,
          padding: '10px 14px'
        },
        {
          id: 'card-1-title',
          type: 'text',
          x: 90,
          y: 700,
          width: 520,
          text: 'Blitzschnell',
          fontFamily: 'Outfit',
          fontSize: 32,
          fontWeight: 700,
          color: '#f0f0f2'
        },
        {
          id: 'card-1-desc',
          type: 'text',
          x: 90,
          y: 750,
          width: 520,
          text: 'Optimiert für 0.00ms Resmon. Kein Lag, keine Verzögerung - nur pure Performance.',
          fontFamily: 'DM Sans',
          fontSize: 16,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.6
        },
        {
          id: 'card-1-stats',
          type: 'text',
          x: 90,
          y: 840,
          width: 520,
          text: '0.00ms • Async • Native',
          fontFamily: 'DM Sans',
          fontSize: 13,
          fontWeight: 500,
          color: '#51cf66'
        },
        // Card 2 (Center, elevated)
        {
          id: 'card-2-bg',
          type: 'container',
          x: 670,
          y: 160,
          width: 580,
          height: 860,
          borderRadius: 16,
          backgroundColor: 'rgba(25,25,28,0.8)',
          border: '1px solid rgba(244,180,0,0.2)',
          boxShadow: '0 30px 80px rgba(0,0,0,0.5), 0 0 40px rgba(244,180,0,0.08)'
        },
        {
          id: 'screen-2',
          type: 'screen3d',
          x: 700,
          y: 190,
          width: 520,
          height: 420,
          src: '',
          placeholder: 'Feature Screenshot 2',
          perspective: 'center',
          borderRadius: 12
        },
        {
          id: 'card-2-icon',
          type: 'chip',
          x: 700,
          y: 640,
          text: '🔧',
          fontFamily: 'DM Sans',
          fontSize: 20,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.12)',
          border: '1px solid rgba(244,180,0,0.25)',
          borderRadius: 10,
          padding: '10px 14px'
        },
        {
          id: 'card-2-badge',
          type: 'chip',
          x: 760,
          y: 645,
          text: 'BELIEBT',
          fontFamily: 'Outfit',
          fontSize: 10,
          fontWeight: 700,
          color: '#0d0d0f',
          backgroundColor: '#f4b400',
          borderRadius: 6,
          padding: '4px 8px'
        },
        {
          id: 'card-2-title',
          type: 'text',
          x: 700,
          y: 700,
          width: 520,
          text: 'Easy Config',
          fontFamily: 'Outfit',
          fontSize: 32,
          fontWeight: 700,
          color: '#f0f0f2'
        },
        {
          id: 'card-2-desc',
          type: 'text',
          x: 700,
          y: 750,
          width: 520,
          text: 'Vollständig konfigurierbar über eine übersichtliche Config-Datei. Keine Programmierung nötig.',
          fontFamily: 'DM Sans',
          fontSize: 16,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.6
        },
        {
          id: 'card-2-stats',
          type: 'text',
          x: 700,
          y: 860,
          width: 520,
          text: 'Lua Config • Kommentiert • Beispiele',
          fontFamily: 'DM Sans',
          fontSize: 13,
          fontWeight: 500,
          color: '#f4b400'
        },
        // Card 3
        {
          id: 'card-3-bg',
          type: 'container',
          x: 1280,
          y: 180,
          width: 580,
          height: 820,
          borderRadius: 16,
          backgroundColor: 'rgba(20,20,22,0.6)',
          border: '1px solid rgba(255,255,255,0.06)',
          boxShadow: '0 20px 60px rgba(0,0,0,0.4)'
        },
        {
          id: 'screen-3',
          type: 'screen3d',
          x: 1310,
          y: 210,
          width: 520,
          height: 400,
          src: '',
          placeholder: 'Feature Screenshot 3',
          perspective: 'right',
          borderRadius: 12
        },
        {
          id: 'card-3-icon',
          type: 'chip',
          x: 1310,
          y: 640,
          text: '🛡️',
          fontFamily: 'DM Sans',
          fontSize: 20,
          color: '#f4b400',
          backgroundColor: 'rgba(244,180,0,0.12)',
          border: '1px solid rgba(244,180,0,0.25)',
          borderRadius: 10,
          padding: '10px 14px'
        },
        {
          id: 'card-3-title',
          type: 'text',
          x: 1310,
          y: 700,
          width: 520,
          text: 'Premium Support',
          fontFamily: 'Outfit',
          fontSize: 32,
          fontWeight: 700,
          color: '#f0f0f2'
        },
        {
          id: 'card-3-desc',
          type: 'text',
          x: 1310,
          y: 750,
          width: 520,
          text: '24/7 Discord Support mit schnellen Reaktionszeiten. Wir helfen dir bei der Einrichtung.',
          fontFamily: 'DM Sans',
          fontSize: 16,
          fontWeight: 400,
          color: '#8b8b93',
          lineHeight: 1.6
        },
        {
          id: 'card-3-stats',
          type: 'text',
          x: 1310,
          y: 840,
          width: 520,
          text: 'Discord • Schnelle Hilfe • Deutsch',
          fontFamily: 'DM Sans',
          fontSize: 13,
          fontWeight: 500,
          color: '#51cf66'
        }
      ]
    }
  ])

  // Selected element
  const selectedElement = computed<MockupElement | null>(() => {
    if (!selectedElementId.value) return null
    return elements.value.find((el: MockupElement) => el.id === selectedElementId.value) || null
  })

  // Actions
  function selectTemplate(templateId: string): void {
    const template = templates.value.find((t: MockupTemplate) => t.id === templateId)
    if (template) {
      currentTemplate.value = template
      elements.value = JSON.parse(JSON.stringify(template.elements))
      canvasWidth.value = template.width
      canvasHeight.value = template.height
      backgroundColor.value = template.transparentBg ? 'transparent' : '#0d0d0f'
      selectedElementId.value = null
    }
  }

  function selectElement(elementId: string): void {
    selectedElementId.value = elementId
  }

  function updateElement(elementId: string, updates: Partial<MockupElement>): void {
    const index = elements.value.findIndex((el: MockupElement) => el.id === elementId)
    if (index !== -1) {
      elements.value[index] = { ...elements.value[index], ...updates }
    }
  }

  function setElementImage(elementId: string, imageUrl: string): void {
    updateElement(elementId, { src: imageUrl })
  }

  function setZoom(newZoom: number): void {
    zoom.value = Math.max(0.25, Math.min(2, newZoom))
  }

  function resetMockup(): void {
    if (currentTemplate.value) {
      elements.value = JSON.parse(JSON.stringify(currentTemplate.value.elements))
    }
    selectedElementId.value = null
  }

  function clearMockup(): void {
    currentTemplate.value = null
    elements.value = []
    selectedElementId.value = null
    canvasWidth.value = 1920
    canvasHeight.value = 1080
    backgroundColor.value = 'transparent'
  }

  function addElement(type: string, options: MockupElementOptions = {}): MockupElement | null {
    const id = `custom-${type}-${Date.now()}`
    let newElement: MockupElement = { id, type }

    // Default positions centered in canvas
    const centerX = Math.round(canvasWidth.value / 2)
    const centerY = Math.round(canvasHeight.value / 2)

    switch (type) {
      case 'text':
        newElement = {
          ...newElement,
          x: centerX - 200,
          y: centerY - 50,
          width: 400,
          text: options.text || 'Neuer Text',
          fontFamily: 'Outfit',
          fontSize: 32,
          fontWeight: 600,
          color: '#ffffff',
          textAlign: 'center',
          ...options,
        }
        break

      case 'image':
        newElement = {
          ...newElement,
          x: centerX - 200,
          y: centerY - 150,
          width: 400,
          height: 300,
          src: '',
          placeholder: 'Bild hier ablegen',
          objectFit: 'cover',
          borderRadius: 12,
          ...options,
        }
        break

      case 'line':
        newElement = {
          ...newElement,
          x: centerX - 100,
          y: centerY,
          width: 200,
          height: 2,
          gradient: 'linear-gradient(90deg, transparent, #f4b400, transparent)',
          ...options,
        }
        break

      case 'button':
        newElement = {
          ...newElement,
          x: centerX - 75,
          y: centerY - 25,
          width: 150,
          height: 50,
          text: options.text || 'Button',
          fontFamily: 'Outfit',
          fontSize: 16,
          fontWeight: 600,
          color: '#0d0d0f',
          backgroundColor: '#f4b400',
          borderRadius: 8,
          ...options,
        }
        break

      case 'chip':
        newElement = {
          ...newElement,
          x: centerX - 60,
          y: centerY - 15,
          text: options.text || '● Tag',
          fontFamily: 'DM Sans',
          fontSize: 12,
          color: '#9898a3',
          backgroundColor: 'rgba(28,28,31,0.72)',
          border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: 10,
          padding: '7px 12px',
          ...options,
        }
        break

      case 'container':
        newElement = {
          ...newElement,
          x: centerX - 200,
          y: centerY - 150,
          width: 400,
          height: 300,
          borderRadius: 12,
          backgroundColor: 'rgba(20,20,22,0.8)',
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 10px 40px rgba(0,0,0,0.4)',
          ...options,
        }
        break

      case 'screen3d':
        newElement = {
          ...newElement,
          x: centerX - 200,
          y: centerY - 150,
          width: 400,
          height: 300,
          src: '',
          placeholder: 'Screenshot',
          perspective: options.perspective || 'center',
          borderRadius: 12,
          ...options,
        }
        break

      default:
        return null
    }

    elements.value.push(newElement)
    selectedElementId.value = id
    return newElement
  }

  function deleteElement(elementId: string): void {
    const index = elements.value.findIndex((el: MockupElement) => el.id === elementId)
    if (index !== -1) {
      elements.value.splice(index, 1)
      if (selectedElementId.value === elementId) {
        selectedElementId.value = null
      }
    }
  }

  function duplicateElement(elementId: string): MockupElement | null {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    if (!element) return null

    const newId = `${element.type}-copy-${Date.now()}`
    const newElement: MockupElement = {
      ...JSON.parse(JSON.stringify(element)),
      id: newId,
      x: (element.x || 0) + 20,
      y: (element.y || 0) + 20,
    }

    elements.value.push(newElement)
    selectedElementId.value = newId
    return newElement
  }

  // ==================== Alignment Functions ====================

  function centerElementHorizontally(elementId: string): void {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    if (!element || element.width === undefined) return

    const newX = Math.round((canvasWidth.value - element.width) / 2)
    updateElement(elementId, { x: newX })
  }

  function centerElementVertically(elementId: string): void {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    if (!element || element.height === undefined) return

    const newY = Math.round((canvasHeight.value - element.height) / 2)
    updateElement(elementId, { y: newY })
  }

  function centerElement(elementId: string): void {
    centerElementHorizontally(elementId)
    centerElementVertically(elementId)
  }

  // ==================== API Functions ====================

  // Fetch custom templates from backend
  async function fetchCustomTemplates(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/mockup/templates')
      customTemplates.value = response.data.data.items || []
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      error.value = axiosErr.response?.data?.message || 'Failed to load templates'
      console.error('Failed to fetch custom templates:', err)
    } finally {
      isLoading.value = false
    }
  }

  // Save current mockup as a custom template
  async function saveAsTemplate(name: string, description: string = '', category: string = 'custom'): Promise<MockupTemplate> {
    isLoading.value = true
    error.value = null
    try {
      const templateData = {
        name,
        description,
        category,
        width: canvasWidth.value,
        height: canvasHeight.value,
        aspectRatio: `${canvasWidth.value}:${canvasHeight.value}`,
        elements: elements.value,
        transparentBg: backgroundColor.value === 'transparent',
      }

      const response = await api.post('/api/v1/mockup/templates', templateData)
      const newTemplate: MockupTemplate = response.data.data

      // Add to local list
      customTemplates.value.unshift(newTemplate)

      return newTemplate
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      error.value = axiosErr.response?.data?.message || 'Failed to save template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Update a custom template
  async function updateCustomTemplate(templateId: string, updates: Partial<MockupTemplate>): Promise<MockupTemplate> {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.put(`/api/v1/mockup/templates/${templateId}`, updates)
      const updatedTemplate: MockupTemplate = response.data.data

      // Update local list
      const index = customTemplates.value.findIndex((t: MockupTemplate) => t.id === templateId)
      if (index !== -1) {
        customTemplates.value[index] = updatedTemplate
      }

      return updatedTemplate
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      error.value = axiosErr.response?.data?.message || 'Failed to update template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Delete a custom template
  async function deleteCustomTemplate(templateId: string): Promise<boolean> {
    isLoading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/mockup/templates/${templateId}`)

      // Remove from local list
      customTemplates.value = customTemplates.value.filter((t: MockupTemplate) => t.id !== templateId)

      return true
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      error.value = axiosErr.response?.data?.message || 'Failed to delete template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Load a custom template
  function loadCustomTemplate(template: MockupTemplate): void {
    currentTemplate.value = {
      ...template,
      isCustom: true,
    }
    elements.value = JSON.parse(JSON.stringify(template.elements))
    canvasWidth.value = template.width
    canvasHeight.value = template.height
    backgroundColor.value = template.transparent_bg ? 'transparent' : '#0d0d0f'
    selectedElementId.value = null
    currentDraftId.value = null
  }

  // ==================== Draft Functions ====================

  // Fetch drafts from backend
  async function fetchDrafts(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/mockup/drafts')
      drafts.value = response.data.data.items || []
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      error.value = axiosErr.response?.data?.message || 'Failed to load drafts'
      console.error('Failed to fetch drafts:', err)
    } finally {
      isLoading.value = false
    }
  }

  // Save current mockup as draft
  async function saveDraft(name: string | null = null): Promise<MockupDraft> {
    isLoading.value = true
    error.value = null
    try {
      const draftData = {
        id: currentDraftId.value,
        name: name || currentTemplate.value?.name || 'Untitled Draft',
        templateId: currentTemplate.value?.id,
        width: canvasWidth.value,
        height: canvasHeight.value,
        elements: elements.value,
      }

      const response = await api.post('/api/v1/mockup/drafts', draftData)
      const savedDraft: MockupDraft = response.data.data

      // Update current draft ID
      currentDraftId.value = savedDraft.id

      // Update local drafts list
      const index = drafts.value.findIndex((d: MockupDraft) => d.id === savedDraft.id)
      if (index !== -1) {
        drafts.value[index] = savedDraft
      } else {
        drafts.value.unshift(savedDraft)
      }

      return savedDraft
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      error.value = axiosErr.response?.data?.message || 'Failed to save draft'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Load a draft
  function loadDraft(draft: MockupDraft): void {
    currentTemplate.value = {
      id: draft.template_id || draft.id,
      name: draft.name || 'Untitled Draft',
      width: draft.width,
      height: draft.height,
      isDraft: true,
      elements: draft.elements,
    }
    elements.value = JSON.parse(JSON.stringify(draft.elements))
    canvasWidth.value = draft.width
    canvasHeight.value = draft.height
    selectedElementId.value = null
    currentDraftId.value = draft.id
  }

  // Delete a draft
  async function deleteDraft(draftId: string): Promise<boolean> {
    isLoading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/mockup/drafts/${draftId}`)

      // Remove from local list
      drafts.value = drafts.value.filter((d: MockupDraft) => d.id !== draftId)

      // Clear current draft ID if this was the active draft
      if (currentDraftId.value === draftId) {
        currentDraftId.value = null
      }

      return true
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      error.value = axiosErr.response?.data?.message || 'Failed to delete draft'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // ==================== Annotation Functions ====================

  // Add annotation to an image element
  function addAnnotation(elementId: string, annotation: Partial<MockupAnnotation>): MockupAnnotation | null {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    if (!element || (element.type !== 'image' && element.type !== 'screen3d')) return null

    const annotationId = `annotation-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
    const newAnnotation: MockupAnnotation = {
      id: annotationId,
      ...annotation,
    }

    if (!element.annotations) {
      element.annotations = []
    }
    element.annotations.push(newAnnotation)

    return newAnnotation
  }

  // Update an annotation
  function updateAnnotation(elementId: string, annotationId: string, updates: Partial<MockupAnnotation>): void {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    if (!element || !element.annotations) return

    const annotationIndex = element.annotations.findIndex((a: MockupAnnotation) => a.id === annotationId)
    if (annotationIndex !== -1) {
      element.annotations[annotationIndex] = {
        ...element.annotations[annotationIndex],
        ...updates,
      }
    }
  }

  // Delete an annotation
  function deleteAnnotation(elementId: string, annotationId: string): void {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    if (!element || !element.annotations) return

    element.annotations = element.annotations.filter((a: MockupAnnotation) => a.id !== annotationId)
  }

  // Get annotations for an element
  function getAnnotations(elementId: string): MockupAnnotation[] {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    return element?.annotations || []
  }

  // Clear all annotations from an element
  function clearAnnotations(elementId: string): void {
    const element = elements.value.find((el: MockupElement) => el.id === elementId)
    if (element) {
      element.annotations = []
    }
  }

  // Computed: All templates (built-in + custom)
  const allTemplates = computed<MockupTemplate[]>(() => {
    // Mark custom templates
    const marked = customTemplates.value.map((t: MockupTemplate) => ({
      ...t,
      isCustom: true,
    }))
    return [...templates.value, ...marked]
  })

  return {
    // State
    currentTemplate,
    elements,
    selectedElementId,
    canvasWidth,
    canvasHeight,
    backgroundColor,
    zoom,
    templates,
    customTemplates,
    drafts,
    currentDraftId,
    isLoading,
    error,

    // Computed
    selectedElement,
    allTemplates,

    // Actions
    selectTemplate,
    selectElement,
    updateElement,
    setElementImage,
    setZoom,
    resetMockup,
    clearMockup,
    addElement,
    deleteElement,
    duplicateElement,

    // Alignment Actions
    centerElementHorizontally,
    centerElementVertically,
    centerElement,

    // API Actions - Templates
    fetchCustomTemplates,
    saveAsTemplate,
    updateCustomTemplate,
    deleteCustomTemplate,
    loadCustomTemplate,

    // API Actions - Drafts
    fetchDrafts,
    saveDraft,
    loadDraft,
    deleteDraft,

    // Annotation Actions
    addAnnotation,
    updateAnnotation,
    deleteAnnotation,
    getAnnotations,
    clearAnnotations,
  }
})
