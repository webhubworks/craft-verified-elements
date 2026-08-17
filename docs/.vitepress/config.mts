import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: "Verified Elements",
  description: "Learn how to make use of the Verified Elements Craft plugin for your CMS content.",
  themeConfig: {
    search: {
      provider: 'local'
    },

    nav: [
      { text: 'Home', link: '/' },
      { text: 'Plugin Store', link: 'https://plugins.craftcms.com/verified-elements' }
    ],

    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'Getting Started', link: '/getting-started' },
          { text: 'Core Concepts', link: '/core-concepts' }
        ]
      },
      {
        text: 'Daily Work',
        items: [
          { text: 'Verifying Content', link: '/verifying-content' },
          { text: 'The Verified Elements Section', link: '/verified-elements-section' },
          { text: 'Bulk Actions', link: '/bulk-actions' },
          { text: 'Working as a Reviewer', link: '/reviewers' },
          { text: 'Dashboard Widgets', link: '/dashboard-widgets' },
          { text: 'Email Notifications', link: '/email-notifications' }
        ]
      },
      {
        text: 'Administration',
        items: [
          { text: 'Settings', link: '/settings' },
          { text: 'Permissions and Editions', link: '/permissions-and-editions' }
        ]
      },
      {
        text: 'Help',
        items: [
          { text: 'FAQ and Troubleshooting', link: '/faq' }
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/webhubworks/craft-verified-elements/' }
    ],

    footer: {
      message: 'Released under The Craft License',
      copyright: 'Copyright © 2026-present webhub GmbH'
    }
  }
})
