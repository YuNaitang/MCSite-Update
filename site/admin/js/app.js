/**
 * Vue 应用入口 + Hash 路由
 */
;(function () {
    const { createApp, computed } = Vue

    const routeMap = {
        '/dashboard': 'dashboard-page',
        '/servers': 'servers-config-page',
        '/gallery': 'gallery-page',
        '/posts': 'posts-page',
        '/posts/edit': 'post-edit-page',
        '/comments': 'comments-page',
        '/whitelist': 'whitelist-page',
        '/users': 'users-page',
        '/settings/site': 'site-settings-page',
        '/settings/features': 'feature-toggle-page',
        '/servers': 'servers-config-page',
        '/friend-links': 'friend-links-page',
        '/update': 'update-page',
        '/theme-market': 'theme-market-page',
    }

    function syncHashRoute() {
        const raw = location.hash.slice(1) || '/dashboard'
        AdminStore.currentRoute = raw.split('?')[0] || '/dashboard'
    }

    const app = createApp({
        setup() {
            const store = AdminStore

            const currentPage = computed(() => {
                const path = store.currentRoute.split('?')[0] || '/dashboard'
                return routeMap[path] || 'dashboard-page'
            })

            window.addEventListener('hashchange', syncHashRoute)

            function onLoginSuccess() {
                store.navigate('/dashboard')
            }

            return { store, currentPage, onLoginSuccess }
        },
    })

    app.component('app-layout', AppLayout)
    app.component('app-sidebar', AppSidebar)
    app.component('app-header', AppHeader)
    app.component('app-pagination', AppPagination)
    app.component('app-status-tag', AppStatusTag)
    app.component('app-search-bar', AppSearchBar)
    app.component('app-image-upload', AppImageUpload)
    app.component('app-rich-editor', AppRichEditor)

    app.component('login-page', LoginPage)
    app.component('dashboard-page', DashboardPage)
    app.component('servers-config-page', ServersConfigPage)
    app.component('gallery-page', GalleryPage)
    app.component('posts-page', PostsPage)
    app.component('post-edit-page', PostEditPage)
    app.component('comments-page', CommentsPage)
    app.component('whitelist-page', WhitelistPage)
    app.component('users-page', UsersPage)
    app.component('site-settings-page', SiteSettingsPage)
    app.component('feature-toggle-page', FeatureTogglePage)
    app.component('friend-links-page', FriendLinksPage)
    app.component('theme-market-page', ThemeMarketPage)
    app.component('update-page', UpdatePage)

    const zhLocale =
        typeof ElementPlusLocaleZhCn !== 'undefined'
            ? ElementPlusLocaleZhCn
            : typeof ElementPlus !== 'undefined' && ElementPlus.lang && ElementPlus.lang.zhCn
              ? ElementPlus.lang.zhCn
              : undefined

    if (zhLocale) {
        app.use(ElementPlus, { locale: zhLocale })
    } else {
        app.use(ElementPlus)
    }

    const icons = typeof ElementPlusIconsVue !== 'undefined' ? ElementPlusIconsVue : {}
    for (const key of Object.keys(icons)) {
        app.component(key, icons[key])
    }

    app.mount('#app')
    syncHashRoute()
})()
