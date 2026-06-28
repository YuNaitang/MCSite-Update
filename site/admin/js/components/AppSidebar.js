/**
 * 侧栏菜单
 */
const AppSidebar = {
    template: `
        <div style="height: 100%; display: flex; flex-direction: column;">
            <div class="sidebar-logo" :class="{ 'sidebar-logo--collapsed': store.sidebarCollapsed }">
                <svg class="sidebar-logo__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z" fill="currentColor" opacity="0.12"/>
                    <path d="M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M12 2v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <rect x="10" y="12" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.5"/>
                </svg>
                <span v-if="!store.sidebarCollapsed" class="sidebar-logo__text">Beacon</span>
            </div>
            <el-menu
                :default-active="activeMenu"
                :collapse="store.sidebarCollapsed"
                :router="false"
                style="border-right: none; flex: 1;"
                @select="onSelect"
            >
                <el-menu-item index="/dashboard">
                    <el-icon><Odometer /></el-icon>
                    <span>仪表盘</span>
                </el-menu-item>

                <el-menu-item-group>
                    <template #title><span class="sidebar-group-title">内容管理</span></template>
                    <el-menu-item index="/posts">
                        <el-icon><Document /></el-icon>
                        <span>动态</span>
                    </el-menu-item>
                    <el-menu-item index="/gallery">
                        <el-icon><Picture /></el-icon>
                        <span>图集</span>
                    </el-menu-item>
                    <el-menu-item index="/comments">
                        <el-icon><ChatDotRound /></el-icon>
                        <span>留言</span>
                    </el-menu-item>
                    <el-menu-item index="/whitelist">
                        <el-icon><UserFilled /></el-icon>
                        <span>白名单</span>
                    </el-menu-item>
                    <el-menu-item index="/friend-links">
                        <el-icon><Link /></el-icon>
                        <span>友情链接</span>
                    </el-menu-item>
                    <el-menu-item index="/media">
                        <el-icon><Folder /></el-icon>
                        <span>资源管理</span>
                    </el-menu-item>
                </el-menu-item-group>

                <template v-if="store.isSuperAdmin">
                    <el-menu-item-group>
                        <template #title><span class="sidebar-group-title">系统管理</span></template>
                        <el-menu-item index="/settings/site">
                            <el-icon><Setting /></el-icon>
                            <span>系统设置</span>
                        </el-menu-item>
                        <el-menu-item index="/servers">
                            <el-icon><Cpu /></el-icon>
                            <span>服务器管理</span>
                        </el-menu-item>
                        <el-menu-item index="/settings/features">
                            <el-icon><Switch /></el-icon>
                            <span>功能开关</span>
                        </el-menu-item>
                        <el-menu-item index="/update">
                            <el-icon><Upload /></el-icon>
                            <span>系统更新</span>
                        </el-menu-item>
                    </el-menu-item-group>
                </template>
            </el-menu>
        </div>
    `,
    setup() {
        const activeMenu = Vue.computed(() => {
            const r = AdminStore.currentRoute
            if (r.startsWith('/posts')) return '/posts'
            if (r === '/settings/theme') return '/theme-market'
            if (r === '/server' || r === '/servers') return '/servers'
            if (r.startsWith('/settings/')) return r
            return r
        })
        function onSelect(index) {
            AdminStore.navigate(index)
        }
        return { store: AdminStore, activeMenu, onSelect }
    },
}
