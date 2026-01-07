import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useMockupStore = defineStore('mockup', () => {
  // API state
  const customTemplates = ref([])
  const drafts = ref([])
  const currentDraftId = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  // Current mockup state
  const currentTemplate = ref(null)
  const elements = ref([])
  const selectedElementId = ref(null)
  const canvasWidth = ref(1920)
  const canvasHeight = ref(1080)
  const backgroundColor = ref('transparent')
  const zoom = ref(1)

  // Template presets for Tebex store
  const templates = ref([
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
          backgroundColor: 'transparent'
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
          backgroundColor: 'transparent'
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
          backgroundColor: 'transparent'
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
          backgroundColor: 'transparent'
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
          backgroundColor: 'transparent'
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
          backgroundColor: 'transparent'
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
          backgroundColor: 'transparent'
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
    }
  ])

  // Selected element
  const selectedElement = computed(() => {
    if (!selectedElementId.value) return null
    return elements.value.find(el => el.id === selectedElementId.value)
  })

  // Actions
  function selectTemplate(templateId) {
    const template = templates.value.find(t => t.id === templateId)
    if (template) {
      currentTemplate.value = template
      elements.value = JSON.parse(JSON.stringify(template.elements))
      canvasWidth.value = template.width
      canvasHeight.value = template.height
      backgroundColor.value = template.transparentBg ? 'transparent' : '#0d0d0f'
      selectedElementId.value = null
    }
  }

  function selectElement(elementId) {
    selectedElementId.value = elementId
  }

  function updateElement(elementId, updates) {
    const index = elements.value.findIndex(el => el.id === elementId)
    if (index !== -1) {
      elements.value[index] = { ...elements.value[index], ...updates }
    }
  }

  function setElementImage(elementId, imageUrl) {
    updateElement(elementId, { src: imageUrl })
  }

  function setZoom(newZoom) {
    zoom.value = Math.max(0.25, Math.min(2, newZoom))
  }

  function resetMockup() {
    if (currentTemplate.value) {
      elements.value = JSON.parse(JSON.stringify(currentTemplate.value.elements))
    }
    selectedElementId.value = null
  }

  function clearMockup() {
    currentTemplate.value = null
    elements.value = []
    selectedElementId.value = null
    canvasWidth.value = 1920
    canvasHeight.value = 1080
    backgroundColor.value = 'transparent'
  }

  function addElement(type, options = {}) {
    const id = `custom-${type}-${Date.now()}`
    let newElement = { id, type }

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

  function deleteElement(elementId) {
    const index = elements.value.findIndex(el => el.id === elementId)
    if (index !== -1) {
      elements.value.splice(index, 1)
      if (selectedElementId.value === elementId) {
        selectedElementId.value = null
      }
    }
  }

  function duplicateElement(elementId) {
    const element = elements.value.find(el => el.id === elementId)
    if (!element) return null

    const newId = `${element.type}-copy-${Date.now()}`
    const newElement = {
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

  function centerElementHorizontally(elementId) {
    const element = elements.value.find(el => el.id === elementId)
    if (!element || element.width === undefined) return

    const newX = Math.round((canvasWidth.value - element.width) / 2)
    updateElement(elementId, { x: newX })
  }

  function centerElementVertically(elementId) {
    const element = elements.value.find(el => el.id === elementId)
    if (!element || element.height === undefined) return

    const newY = Math.round((canvasHeight.value - element.height) / 2)
    updateElement(elementId, { y: newY })
  }

  function centerElement(elementId) {
    centerElementHorizontally(elementId)
    centerElementVertically(elementId)
  }

  // ==================== API Functions ====================

  // Fetch custom templates from backend
  async function fetchCustomTemplates() {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/mockup/templates')
      customTemplates.value = response.data.data.items || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to load templates'
      console.error('Failed to fetch custom templates:', err)
    } finally {
      isLoading.value = false
    }
  }

  // Save current mockup as a custom template
  async function saveAsTemplate(name, description = '', category = 'custom') {
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
      const newTemplate = response.data.data

      // Add to local list
      customTemplates.value.unshift(newTemplate)

      return newTemplate
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to save template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Update a custom template
  async function updateCustomTemplate(templateId, updates) {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.put(`/api/v1/mockup/templates/${templateId}`, updates)
      const updatedTemplate = response.data.data

      // Update local list
      const index = customTemplates.value.findIndex(t => t.id === templateId)
      if (index !== -1) {
        customTemplates.value[index] = updatedTemplate
      }

      return updatedTemplate
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Delete a custom template
  async function deleteCustomTemplate(templateId) {
    isLoading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/mockup/templates/${templateId}`)

      // Remove from local list
      customTemplates.value = customTemplates.value.filter(t => t.id !== templateId)

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Load a custom template
  function loadCustomTemplate(template) {
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
  async function fetchDrafts() {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/mockup/drafts')
      drafts.value = response.data.data.items || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to load drafts'
      console.error('Failed to fetch drafts:', err)
    } finally {
      isLoading.value = false
    }
  }

  // Save current mockup as draft
  async function saveDraft(name = null) {
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
      const savedDraft = response.data.data

      // Update current draft ID
      currentDraftId.value = savedDraft.id

      // Update local drafts list
      const index = drafts.value.findIndex(d => d.id === savedDraft.id)
      if (index !== -1) {
        drafts.value[index] = savedDraft
      } else {
        drafts.value.unshift(savedDraft)
      }

      return savedDraft
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to save draft'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Load a draft
  function loadDraft(draft) {
    currentTemplate.value = {
      id: draft.template_id,
      name: draft.name,
      width: draft.width,
      height: draft.height,
      isDraft: true,
    }
    elements.value = JSON.parse(JSON.stringify(draft.elements))
    canvasWidth.value = draft.width
    canvasHeight.value = draft.height
    selectedElementId.value = null
    currentDraftId.value = draft.id
  }

  // Delete a draft
  async function deleteDraft(draftId) {
    isLoading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/mockup/drafts/${draftId}`)

      // Remove from local list
      drafts.value = drafts.value.filter(d => d.id !== draftId)

      // Clear current draft ID if this was the active draft
      if (currentDraftId.value === draftId) {
        currentDraftId.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete draft'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // ==================== Annotation Functions ====================

  // Add annotation to an image element
  function addAnnotation(elementId, annotation) {
    const element = elements.value.find(el => el.id === elementId)
    if (!element || (element.type !== 'image' && element.type !== 'screen3d')) return null

    const annotationId = `annotation-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
    const newAnnotation = {
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
  function updateAnnotation(elementId, annotationId, updates) {
    const element = elements.value.find(el => el.id === elementId)
    if (!element || !element.annotations) return

    const annotationIndex = element.annotations.findIndex(a => a.id === annotationId)
    if (annotationIndex !== -1) {
      element.annotations[annotationIndex] = {
        ...element.annotations[annotationIndex],
        ...updates,
      }
    }
  }

  // Delete an annotation
  function deleteAnnotation(elementId, annotationId) {
    const element = elements.value.find(el => el.id === elementId)
    if (!element || !element.annotations) return

    element.annotations = element.annotations.filter(a => a.id !== annotationId)
  }

  // Get annotations for an element
  function getAnnotations(elementId) {
    const element = elements.value.find(el => el.id === elementId)
    return element?.annotations || []
  }

  // Clear all annotations from an element
  function clearAnnotations(elementId) {
    const element = elements.value.find(el => el.id === elementId)
    if (element) {
      element.annotations = []
    }
  }

  // Computed: All templates (built-in + custom)
  const allTemplates = computed(() => {
    // Mark custom templates
    const marked = customTemplates.value.map(t => ({
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
