/**
 * 系统设置（结构化分组）
 *
 * ── 站点信息
 * ── 首页 Hero 区域
 * ── 各板块标题与描述
 * ── 社交与联系方式
 * ── 页脚信息
 * ── 自定义代码
 * ── 计划任务
 * ── 系统更新 + 版本备份
 *
 * 每个卡片底部留有「扩展槽」用于未来新增字段。
 */
const SiteSettingsPage = {
    template: `
        <div class="page-container">
            <div class="page-header">
                <h2 class="page-header__title">系统设置</h2>
                <el-button type="primary" :loading="saving" @click="save">保存</el-button>
            </div>

            <!-- ──────── A. 站点信息 ──────── -->
            <div class="card-box" v-loading="loading">
                <h3>站点信息</h3>
                <el-form ref="formRef" :model="form" label-width="140px" style="max-width: 720px;">
                    <el-form-item label="站点名称">
                        <el-input v-model="form.site_name" placeholder="显示在浏览器标题与页脚" />
                    </el-form-item>
                    <el-form-item label="站点描述">
                        <el-input v-model="form.site_description" type="textarea" :rows="2" placeholder="SEO 描述" />
                    </el-form-item>
                    <el-form-item label="关键词">
                        <el-input v-model="form.site_keywords" placeholder="逗号分隔，用于 SEO" />
                    </el-form-item>
                    <el-form-item label="Logo">
                        <div style="display:flex;align-items:flex-start;gap:12px;width:100%;">
                            <app-image-upload v-model="form.logo_url" />
                            <el-input v-model="form.logo_url" placeholder="Logo 图片路径或 URL" style="flex:1;" />
                        </div>
                    </el-form-item>
                    <el-form-item label="Favicon">
                        <div style="display:flex;align-items:flex-start;gap:12px;width:100%;">
                            <app-image-upload v-model="form.favicon_url" />
                            <el-input v-model="form.favicon_url" placeholder="浏览器标签图标 URL" style="flex:1;" />
                        </div>
                    </el-form-item>
                    <el-form-item label="站点 URL">
                        <el-input v-model="form.site_url" placeholder="https://www.yunaitang.top" disabled />
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">安装时设定，不可修改</div>
                    </el-form-item>
                    <el-form-item label="服务器地址">
                        <el-input v-model="form.server_address_display" placeholder="显示在首页的地址" />
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">留空则自动从服务器列表推断</div>
                    </el-form-item>
                </el-form>
            </div>

            <!-- ──────── B. Hero 区域 ──────── -->
            <div class="card-box" v-loading="loading">
                <h3>首页 Hero 区域</h3>
                <el-form :model="form" label-width="140px" style="max-width: 720px;">
                    <el-form-item label="主标题">
                        <el-input v-model="form.hero_title" placeholder="欢迎来到服务器" />
                    </el-form-item>
                    <el-form-item label="副标题">
                        <el-input v-model="form.hero_subtitle" placeholder="副标题或服务器口号" />
                    </el-form-item>
                    <el-form-item label="描述文字">
                        <el-input v-model="form.hero_description" type="textarea" :rows="2" placeholder="一段简短的介绍" />
                    </el-form-item>
                    <el-form-item label="背景图片">
                        <div style="display:flex;align-items:flex-start;gap:12px;width:100%;">
                            <app-image-upload v-model="form.hero_bg_image" />
                            <el-input v-model="form.hero_bg_image" placeholder="建议 1920x1080+" style="flex:1;" />
                        </div>
                    </el-form-item>
                </el-form>
            </div>

            <!-- ──────── C. 各板块标题与描述 ──────── -->
            <div class="card-box" v-loading="loading">
                <h3>各板块标题与描述</h3>
                <el-form :model="form" label-width="140px" style="max-width: 720px;">
                    <el-form-item label="服务器状态">
                        <el-input v-model="form.section_servers_title" placeholder="服务器状态" style="margin-bottom:6px;" />
                        <el-input v-model="form.section_servers_description" type="textarea" :rows="1" placeholder="对各服务器的简要介绍" />
                    </el-form-item>
                    <el-divider style="margin:12px 0;" />
                    <el-form-item label="服务器图集">
                        <el-input v-model="form.section_gallery_title" placeholder="服务器图集" style="margin-bottom:6px;" />
                        <el-input v-model="form.section_gallery_description" type="textarea" :rows="1" placeholder="展现服务器的精彩瞬间" />
                    </el-form-item>
                    <el-divider style="margin:12px 0;" />
                    <el-form-item label="服务器动态">
                        <el-input v-model="form.section_news_title" placeholder="服务器动态" style="margin-bottom:6px;" />
                        <el-input v-model="form.section_news_description" type="textarea" :rows="1" placeholder="了解最新的服务器资讯" />
                    </el-form-item>
                    <el-divider style="margin:12px 0;" />
                    <el-form-item label="留言板">
                        <el-input v-model="form.section_comments_title" placeholder="留言板" style="margin-bottom:6px;" />
                        <el-input v-model="form.section_comments_description" type="textarea" :rows="1" placeholder="畅所欲言，留下你的想法" />
                    </el-form-item>
                </el-form>
            </div>

            <!-- ──────── D. 社交与联系方式 ──────── -->
            <div class="card-box" v-loading="loading">
                <h3>社交与联系方式</h3>
                <el-form :model="form" label-width="140px" style="max-width: 720px;">
                    <el-form-item label="QQ 群名称">
                        <el-input v-model="form.qq_group_name" placeholder="如 官方QQ群" />
                    </el-form-item>
                    <el-form-item label="QQ 群链接">
                        <el-input v-model="form.qq_group_link" placeholder="如 https://qm.qq.com/q/xxxxx" />
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">留空则不显示 QQ 群</div>
                    </el-form-item>
                    <el-divider />
                    <el-form-item label="Kook 名称">
                        <el-input v-model="form.discord_name" placeholder="如 Kook 社区" />
                    </el-form-item>
                    <el-form-item label="Kook 链接">
                        <el-input v-model="form.discord_link" placeholder="如 https://kook.top/xxxxx" />
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">留空则不显示 Kook</div>
                    </el-form-item>
                    <el-divider />
                    <el-form-item label="其他联系方式">
                        <el-input v-model="form.custom_contacts" type="textarea" :rows="3"
                            placeholder="Bilibili | https://space.bilibili.com/xxx&#10;QQ频道 | https://pd.qq.com/xxx" />
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">每行一条，名称与链接用竖线 | 分隔</div>
                    </el-form-item>
                </el-form>
            </div>

            <!-- ──────── E. 页脚信息 ──────── -->
            <div class="card-box" v-loading="loading">
                <h3>页脚信息</h3>
                <el-form :model="form" label-width="140px" style="max-width: 720px;">
                    <el-form-item label="版权信息">
                        <el-input v-model="form.footer_copyright" placeholder="留空则自动生成 © 年份 All rights reserved." />
                    </el-form-item>
                    <el-form-item label="页脚描述">
                        <el-input v-model="form.footer_description" type="textarea" :rows="2" placeholder="显示在页脚的描述文字" />
                    </el-form-item>
                    <el-form-item label="ICP 备案号">
                        <el-input v-model="form.icp_number" placeholder="如 京ICP备xxxxxxxx号" />
                    </el-form-item>
                    <el-form-item label="备案号链接">
                        <el-input v-model="form.icp_link" placeholder="如 https://beian.miit.gov.cn/" />
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">填写后备案号将显示为可点击链接</div>
                    </el-form-item>
                    <el-form-item label="页脚 HTML">
                        <el-input v-model="form.footer_custom_html" type="textarea" :rows="3" placeholder="自定义页脚 HTML 代码" />
                    </el-form-item>
                </el-form>
            </div>

            <!-- ──────── F. 自定义代码 ──────── -->
            <div class="card-box" v-loading="loading">
                <h3>自定义代码</h3>
                <el-form :model="form" label-width="140px" style="max-width: 720px;">
                    <el-form-item label="Head 代码">
                        <el-input v-model="form.custom_head_html" type="textarea" :rows="5"
                            placeholder="统计代码、广告代码等，会注入到页面 &lt;head&gt; 中" />
                    </el-form-item>
                    <el-form-item label="自定义 CSS">
                        <el-input v-model="form.custom_css" type="textarea" :rows="5"
                            placeholder="自定义样式代码，注入到页面 &lt;head&gt;" />
                    </el-form-item>
                </el-form>
            </div>

            <!-- ──────── G. 计划任务 ──────── -->
            <div class="card-box" v-loading="cronLoading">
                <h3>计划任务</h3>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
                    <span style="width: 8px; height: 8px; border-radius: 50; display: inline-block;"
                        :style="{ background: cron.is_running ? '#10b981' : '#ef4444', borderRadius: '50%' }"></span>
                    <span style="font-size: 14px; font-weight: 600;">{{ cron.is_running ? '运行中' : '未运行' }}</span>
                    <span v-if="cron.cache_age_seconds != null" style="font-size: 13px; color: var(--text-muted); margin-left: 8px;">
                        {{ formatAge(cron.cache_age_seconds) }}</span>
                </div>
                <el-descriptions :column="1" border size="small" style="max-width: 720px;">
                    <el-descriptions-item label="最后执行时间">{{ cron.last_run || '从未执行' }}</el-descriptions-item>
                    <el-descriptions-item label="最后日志时间">{{ cron.last_log || '无记录' }}</el-descriptions-item>
                    <el-descriptions-item label="今日查询次数">{{ cron.today_logs }}</el-descriptions-item>
                    <el-descriptions-item label="历史总记录数">{{ cron.total_logs }}</el-descriptions-item>
                    <el-descriptions-item label="Shell 命令">
                        <code style="font-size: 12px; background: var(--bg-deep); padding: 4px 10px; border-radius: 6px; user-select: all;">{{ cron.cron_command }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="URL 调用">
                        <code style="font-size: 12px; background: var(--bg-deep); padding: 4px 10px; border-radius: 6px; user-select: all;">{{ cron.cron_url }}</code>
                    </el-descriptions-item>
                </el-descriptions>
                <div style="margin-top: 16px; font-size: 12px; color: var(--text-muted); line-height: 1.8;">
                    <p>在宝塔面板「计划任务」中添加 Shell 脚本任务，执行周期设为<strong style="color: var(--text-secondary);">每 1 分钟</strong>，脚本内容填写上方 Shell 命令。</p>
                </div>
            </div>

            <!-- ──────── H. 系统更新 + 版本备份 ──────── -->
            <div class="card-box">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                    <h3 style="margin:0 !important;">系统更新</h3>
                    <el-button :loading="checking" size="small" @click="checkUpdate">
                        <el-icon><Refresh /></el-icon>检查更新
                    </el-button>
                </div>
                <div class="update-version-grid" style="margin-bottom:20px;">
                    <div class="update-version-item">
                        <span class="update-version-label">当前版本</span>
                        <span class="update-version-value">{{ updateInfoBar.current || '...' }}</span>
                    </div>
                    <div class="update-version-item">
                        <span class="update-version-label">PHP 版本</span>
                        <span class="update-version-value">{{ updateInfoBar.php_version || '...' }}</span>
                    </div>
                    <div class="update-version-item" v-if="updateInfoBar.pending_migrations > 0">
                        <span class="update-version-label">待执行迁移</span>
                        <span class="update-version-value" style="color:#d97706;">{{ updateInfoBar.pending_migrations }} 个</span>
                    </div>
                </div>

                <div v-if="updateInfoBar.pending_migrations > 0" style="margin-bottom: 20px;">
                    <el-button type="warning" :loading="migrating" @click="runMigration">
                        <el-icon><Upload /></el-icon>执行数据库迁移
                    </el-button>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:8px;">更新表结构，不影响现有数据</span>
                </div>

                <div v-if="updateCheck">
                    <div v-if="updateCheck.has_update" class="update-available">
                        <div class="update-available__header">
                            <div>
                                <div class="update-available__badge">有新版本</div>
                                <h3 style="margin:8px 0 4px;">v{{ updateCheck.latest_version }}</h3>
                                <p style="color:var(--text-muted);font-size:12px;margin:0;">发布于 {{ updateCheck.released_at || '未知' }}</p>
                            </div>
                            <el-button type="primary" :loading="updating" :disabled="updating" @click="confirmUpdate">
                                <el-icon v-if="!updating"><Upload /></el-icon>
                                {{ updating ? updateStatus : '立即更新' }}
                            </el-button>
                        </div>
                        <div v-if="updateCheck.changelog" class="update-changelog">
                            <h3>更新日志</h3>
                            <div class="update-changelog__content" v-html="renderChangelog(updateCheck.changelog)"></div>
                        </div>
                    </div>
                    <div v-else class="update-latest">
                        <el-icon style="font-size:36px;color:var(--text-muted);"><CircleCheck /></el-icon>
                        <div>
                            <p style="font-size:14px;font-weight:600;margin:0 0 2px;">已是最新版本</p>
                            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">当前 v{{ updateCheck.current }}，无需更新</p>
                        </div>
                    </div>
                    <div v-if="updateCheck.error" style="margin-top:12px;">
                        <el-alert :title="updateCheck.error" type="warning" :closable="false" show-icon />
                    </div>
                </div>

                <div v-if="updateResult" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-subtle);">
                    <h3>更新结果</h3>
                    <div v-for="(step, i) in updateResult.steps" :key="i" class="update-step">
                        <el-icon v-if="step.status === 'ok'" style="color:#10b981;"><CircleCheck /></el-icon>
                        <el-icon v-else-if="step.status === 'error'" style="color:#ef4444;"><CircleClose /></el-icon>
                        <el-icon v-else><Loading /></el-icon>
                        <span>{{ stepLabel(step.step) }}</span>
                        <span v-if="step.file" style="color:var(--text-muted);font-size:12px;margin-left:8px;">{{ step.file }}</span>
                    </div>
                    <el-alert v-if="updateResult.steps && updateResult.steps.length > 0"
                        title="更新完成，建议刷新页面以加载新版本。" type="success" show-icon style="margin-top:12px;">
                        <el-button size="small" style="margin-top:8px;" @click="reloadPage">刷新页面</el-button>
                    </el-alert>
                </div>
            </div>

            <div class="card-box" v-if="backups.length > 0">
                <h3>版本备份</h3>
                <el-table v-if="!store.isMobile" :data="backups" stripe>
                    <el-table-column prop="file" label="文件名" min-width="200" />
                    <el-table-column prop="size_human" label="大小" width="100" />
                    <el-table-column prop="created_at" label="备份时间" width="170" />
                </el-table>
                <div v-else class="mobile-list">
                    <div v-for="b in backups" :key="b.file" class="mobile-card">
                        <div style="font-size:13px;font-weight:600;word-break:break-all;">{{ b.file }}</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">{{ b.size_human }} · {{ b.created_at }}</div>
                    </div>
                </div>
            </div>
        </div>
    `,
    setup() {
        const { ref, reactive, onMounted } = Vue
        const store = AdminStore
        const loading = ref(false)
        const saving = ref(false)
        const formRef = ref(null)
        const form = reactive({
            // A. 站点信息
            site_name: '', site_description: '', site_keywords: '',
            logo_url: '', favicon_url: '',
            site_url: '', server_address_display: '',

            // B. Hero 区域
            hero_title: '', hero_subtitle: '', hero_description: '', hero_bg_image: '',

            // C. 各板块标题
            section_servers_title: '', section_servers_description: '',
            section_gallery_title: '', section_gallery_description: '',
            section_news_title: '', section_news_description: '',
            section_comments_title: '', section_comments_description: '',

            // D. 社交与联系方式
            qq_group_name: '', qq_group_link: '',
            discord_name: '', discord_link: '',
            custom_contacts: '',

            // E. 页脚信息
            footer_copyright: '', footer_description: '',
            icp_number: '', icp_link: '',
            footer_custom_html: '',

            // F. 自定义代码
            custom_head_html: '', custom_css: '',
        })

        const cronLoading = ref(false)
        const cron = reactive({
            is_running: false, last_run: null, last_log: null,
            cache_age_seconds: null, total_logs: 0, today_logs: 0,
            cron_command: '', cron_url: '',
        })

        const checking = ref(false)
        const updating = ref(false)
        const migrating = ref(false)
        const updateStatus = ref('')
        const updateInfoBar = reactive({ current: '', php_version: '', pending_migrations: 0 })
        const updateCheck = ref(null)
        const updateResult = ref(null)
        const backups = ref([])

        function formatAge(seconds) {
            if (seconds == null) return ''
            if (seconds < 60) return seconds + ' 秒前执行'
            if (seconds < 3600) return Math.floor(seconds / 60) + ' 分钟前执行'
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' 小时前执行'
            return Math.floor(seconds / 86400) + ' 天前执行'
        }

        async function load() {
            loading.value = true
            try {
                const res = await AdminApi.get('/settings/site')
                const d = res.data || {}
                Object.keys(form).forEach((k) => { if (d[k] !== undefined && d[k] !== null) form[k] = d[k] })
            } finally { loading.value = false }
        }

        async function loadCron() {
            cronLoading.value = true
            try {
                const res = await AdminApi.get('/cron/status')
                Object.assign(cron, res.data || {})
            } catch (_) {} finally { cronLoading.value = false }
        }

        async function save() {
            saving.value = true
            try {
                // 只发送 form 中定义的字段
                const payload = { ...form }
                await AdminApi.put('/settings/site', payload)
                ElementPlus.ElMessage.success('已保存')
            } finally { saving.value = false }
        }

        async function loadUpdateInfo() {
            try {
                const res = await AdminApi.get('/update/version')
                Object.assign(updateInfoBar, res.data || {})
            } catch (_) {}
        }

        async function loadBackups() {
            try {
                const res = await AdminApi.get('/update/backups')
                backups.value = res.data || []
            } catch (_) {}
        }

        async function checkUpdate() {
            checking.value = true
            updateResult.value = null
            try {
                const res = await AdminApi.get('/update/check')
                updateCheck.value = res.data || {}
            } catch (e) {
                updateCheck.value = { has_update: false, error: '检查失败: ' + (e.message || '未知错误') }
            } finally { checking.value = false }
        }

        async function confirmUpdate() {
            try {
                await ElementPlus.ElMessageBox.confirm(
                    '更新将自动备份当前版本，然后从 GitHub 拉取最新代码。确定继续？',
                    '确认更新', { type: 'warning', confirmButtonText: '开始更新', cancelButtonText: '取消' }
                )
            } catch (_) { return }
            updating.value = true
            updateResult.value = null
            updateStatus.value = '更新中...'
            try {
                const res = await AdminApi.post('/update/apply', {})
                updateResult.value = res.data || {}
                updateCheck.value = null
                await loadUpdateInfo()
                await loadBackups()
                ElementPlus.ElMessage.success('更新成功！')
            } catch (e) {
                ElementPlus.ElMessage.error('更新失败: ' + (e.message || '未知错误'))
            } finally { updating.value = false; updateStatus.value = '' }
        }

        async function runMigration() {
            try {
                await ElementPlus.ElMessageBox.confirm(
                    '将执行数据库结构更新，不会影响现有数据。确定继续？',
                    '执行数据库迁移', { type: 'warning', confirmButtonText: '执行', cancelButtonText: '取消' }
                )
            } catch (_) { return }
            migrating.value = true
            try {
                const res = await AdminApi.post('/update/migrate', {})
                const list = res.data?.migrations || []
                const okCount = list.filter(m => m.status === 'ok').length
                ElementPlus.ElMessage.success(`迁移完成：${okCount} 个成功`)
                updateInfoBar.pending_migrations = 0
            } catch (e) {
                ElementPlus.ElMessage.error('迁移失败: ' + (e.message || '未知错误'))
            } finally { migrating.value = false }
        }

        function stepLabel(step) {
            return { backup: '备份当前版本', download: '下载更新包', install: '安装更新' }[step] || step
        }

        function renderChangelog(text) {
            if (!text) return ''
            return text
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/^### (.+)$/gm, '<h4 style="margin:16px 0 6px;font-size:14px;">$1</h4>')
                .replace(/^- (.+)$/gm, '<div style="padding:2px 0 2px 16px;font-size:13px;color:var(--text-secondary);">· $1</div>')
                .replace(/\n/g, '')
        }

        function reloadPage() { location.reload() }

        onMounted(() => {
            if (!AdminStore.isSuperAdmin) {
                ElementPlus.ElMessage.error('无权访问')
                AdminStore.navigate('/dashboard')
                return
            }
            load()
            loadCron()
            loadUpdateInfo()
            loadBackups()
            checkUpdate()
        })

        return {
            store, loading, saving, formRef, form, save,
            cronLoading, cron, formatAge,
            checking, updating, migrating, updateStatus, updateInfoBar,
            updateCheck, updateResult, backups,
            checkUpdate, confirmUpdate, runMigration, stepLabel, renderChangelog, reloadPage,
        }
    },
}
