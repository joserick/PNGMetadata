import { defineUserConfig } from 'vuepress'
import { defaultTheme } from '@vuepress/theme-default'

/**
 * Configuración de punto de partida para desplegar esta carpeta con VuePress 2.
 * La documentación es bilingüe: cada página existe en `es/` y `en/`.
 *
 * Ajusta `base`, `title` y `description` cuando copies `docs/` a tu proyecto.
 */
export default defineUserConfig({
  base: '/',
  title: 'PNGMetadata',
  description: 'Extracting PNG metadata natively in PHP',

  locales: {
    '/es/': {
      lang: 'es-ES',
      title: 'PNGMetadata',
      description: 'Extracción nativa de metadatos PNG en PHP',
    },
    '/en/': {
      lang: 'en-US',
      title: 'PNGMetadata',
      description: 'Extracting PNG metadata natively in PHP',
    },
  },

  theme: defaultTheme({
    locales: {
      '/es/': {
        selectLanguageText: 'Idiomas',
        selectLanguageName: 'Español',
        sidebar: [
          {
            text: 'Introducción',
            collapsible: false,
            children: [
              '/es/introduction/README.md',
              '/es/introduction/what-is-png.md',
              '/es/introduction/binary-format.md',
              '/es/introduction/chunk-anatomy.md',
            ],
          },
          {
            text: 'Arquitectura del proyecto',
            collapsible: false,
            children: [
              '/es/architecture/README.md',
              '/es/architecture/architecture.md',
              '/es/architecture/api.md',
            ],
          },
          {
            text: 'Chunks y metadatos',
            collapsible: false,
            children: [
              '/es/chunks/README.md',
              '/es/chunks/ihdr.md',
              '/es/chunks/text.md',
              '/es/chunks/exif.md',
              '/es/chunks/xmp.md',
              '/es/chunks/color.md',
              '/es/chunks/background-transparency.md',
              '/es/chunks/miscellaneous.md',
              '/es/chunks/png-3-hdr-apng.md',
            ],
          },
          '/es/glossary.md',
        ],
      },
      '/en/': {
        selectLanguageText: 'Languages',
        selectLanguageName: 'English',
        sidebar: [
          {
            text: 'Introduction',
            collapsible: false,
            children: [
              '/en/introduction/README.md',
              '/en/introduction/what-is-png.md',
              '/en/introduction/binary-format.md',
              '/en/introduction/chunk-anatomy.md',
            ],
          },
          {
            text: 'Project architecture',
            collapsible: false,
            children: [
              '/en/architecture/README.md',
              '/en/architecture/architecture.md',
              '/en/architecture/api.md',
            ],
          },
          {
            text: 'Chunks and metadata',
            collapsible: false,
            children: [
              '/en/chunks/README.md',
              '/en/chunks/ihdr.md',
              '/en/chunks/text.md',
              '/en/chunks/exif.md',
              '/en/chunks/xmp.md',
              '/en/chunks/color.md',
              '/en/chunks/background-transparency.md',
              '/en/chunks/miscellaneous.md',
              '/en/chunks/png-3-hdr-apng.md',
            ],
          },
          '/en/glossary.md',
        ],
      },
    },
  }),
})
