import {defineConfig} from 'vitepress'
import {readFileSync} from 'node:fs'

// The release workflow writes the current git tag to .git-tag in the repo
// root before building the docs. Locally the file usually doesn't exist,
// in which case the footer simply omits the version.
function readGitTag(): string {
    try {
        const tag = readFileSync(new URL('../../.git-tag', import.meta.url), 'utf8').trim()
        return tag && !tag.startsWith('v') ? `v${tag}` : tag
    } catch {
        return ''
    }
}

const gitTag = readGitTag()

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: "Verified Elements",
    description: "Learn how to make use of the Verified Elements Craft plugin for your CMS content.",
    base: '/craft/verified-elements/',
    locales: {
        root: {
            label: 'English',
            lang: 'en'
        },
        de: {
            label: 'Deutsch',
            lang: 'de',
            link: '/de/',
            markdown: {
                container: {
                    tipLabel: 'TIPP',
                    noteLabel: 'HINWEIS',
                    warningLabel: 'WARNUNG',
                    infoLabel: 'INFO'
                }
            },
            themeConfig: {
                nav: [
                    {text: 'Start', link: '/de/'},
                    {text: 'Plugin Store', link: 'https://plugins.craftcms.com/verified-elements'}
                ],

                sidebar: [
                    {
                        text: 'Einführung',
                        items: [
                            {text: 'Erste Schritte', link: '/de/getting-started'},
                            {text: 'Grundbegriffe', link: '/de/core-concepts'},
                            {text: 'Control-Panel-Lokalisierung', link: '/de/localization'}
                        ]
                    },
                    {
                        text: 'Tägliche Arbeit',
                        items: [
                            {text: 'Inhalte verifizieren', link: '/de/verifying-content'},
                            {text: 'Der Plugin-CP-Bereich', link: '/de/plugin-cp-section'},
                            {text: 'Massenaktionen', link: '/de/bulk-actions'},
                            {text: 'Arbeiten als Prüfer', link: '/de/reviewers'},
                            {text: 'Dashboard-Widgets', link: '/de/dashboard-widgets'},
                            {text: 'E-Mail-Benachrichtigungen', link: '/de/email-notifications'}
                        ]
                    },
                    {
                        text: 'Administration',
                        items: [
                            {text: 'Einstellungen', link: '/de/settings'},
                            {text: 'Berechtigungen und Editionen', link: '/de/permissions-and-editions'}
                        ]
                    },
                    {
                        text: 'Entwicklung',
                        items: [
                            {text: 'Abfragen und Eigenschaften', link: '/de/querying-and-properties'},
                        ]
                    },
                    {
                        text: 'Hilfe',
                        items: [
                            {text: 'FAQ und Problemlösungen', link: '/de/faq'}
                        ]
                    }
                ]
            }
        }
    },
    themeConfig: {
        search: {
            provider: 'local'
        },

        nav: [
            {text: 'Home', link: '/'},
            {text: 'Plugin Store', link: 'https://plugins.craftcms.com/verified-elements'}
        ],

        sidebar: [
            {
                text: 'Introduction',
                items: [
                    {text: 'Getting Started', link: '/getting-started'},
                    {text: 'Core Concepts', link: '/core-concepts'},
                    {text: 'CP Localization', link: '/localization'},
                ]
            },
            {
                text: 'Daily Work',
                items: [
                    {text: 'Verifying Content', link: '/verifying-content'},
                    {text: 'The Plugin\'s CP Section', link: '/plugin-cp-section'},
                    {text: 'Bulk Actions', link: '/bulk-actions'},
                    {text: 'Working as a Reviewer', link: '/reviewers'},
                    {text: 'Dashboard Widgets', link: '/dashboard-widgets'},
                    {text: 'Email Notifications', link: '/email-notifications'}
                ]
            },
            {
                text: 'Administration',
                items: [
                    {text: 'Settings', link: '/settings'},
                    {text: 'Permissions and Editions', link: '/permissions-and-editions'}
                ]
            },
            {
                text: 'Development',
                items: [
                    {text: 'Querying and Properties', link: '/querying-and-properties'},
                ]
            },
            {
                text: 'Help',
                items: [
                    {text: 'FAQ and Troubleshooting', link: '/faq'}
                ]
            }
        ],

        socialLinks: [
            {icon: 'github', link: 'https://github.com/webhubworks/craft-verified-elements/'}
        ],

        footer: {
            message: `Released under The Craft License${gitTag ? ` (${gitTag})` : ''}`,
            copyright: 'Copyright © 2026-present webhub GmbH'
        }
    }
})
