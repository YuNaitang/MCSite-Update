/**
 * 资源管理 — 浏览/编辑/删除已上传的资源
 */
const MediaPage = {
    template: `
        <div class="page-container">
            <div class="page-header">
                <h2 class="page-header__title">资源管理</h2>
                <div style="display:flex;gap:8px;">
                    <el-upload
                        :show-file-list="false"
                        :http-request="uploadFile"
                        accept="image/*"
                    >
                        <el-button type="primary" :loading="uploading">
                            <el-icon><Upload /></el-icon>上传图片
                        </el-button>
                    </el-upload>
                    <el-button :loading="loading" @click="loadItems">
                        <el-icon><Refresh /></el-icon>刷新
                    </el-button>
                </div>
            </div>

            <!-- 筛选栏 -->
            <div style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <el-select v-model="filters.source" clearable placeholder="来源筛选" style="width:140px;" @change="loadItems">
                    <el-option label="全部来源" value="" />
                    <el-option v-for="s in sourceOptions" :key="s.value" :label="s.label" :value="s.value" />
                </el-select>
                <el-select v-model="filters.is_public" clearable placeholder="访问权限" style="width:140px;" @change="loadItems">
                    <el-option label="全部" value="" />
                    <el-option label="公开" :value="1" />
                    <el-option label="私密" :value="0" />
                </el-select>
                <el-input v-model="filters.keyword" placeholder="搜索文件名" style="width:200px;" clearable @keyup.enter="loadItems" />
                <el-button @click="loadItems" size="default">
                    <el-icon><Search /></el-icon>搜索
                </el-button>
            </div>

            <div class="card-box" v-loading="loading">
                <template v-if="items.length > 0">
                    <div class="media-grid">
                        <div v-for="item in items" :key="item.path" class="media-item">
                            <div class="media-item__preview" @click="preview(item)">
                                <img :src="item.webp_url || item.url" :alt="item.file_name" loading="lazy" />
                                <div class="media-item__overlay">
                                    <el-icon style="font-size:32px;color:#fff;"><View /></el-icon>
                                </div>
                            </div>
                            <div class="media-item__info">
                                <div class="media-item__name" :title="item.file_name">
                                    <el-input
                                        v-if="editingId === item.path"
                                        v-model="editForm.file_name"
                                        size="small"
                                        @keyup.enter="saveEdit(item)"
                                        @blur="saveEdit(item)"
                                        style="width:100%;"
                                    />
                                    <span v-else class="media-item__name-text" @click="startEdit(item)">{{ item.file_name }}</span>
                                </div>
                                <div class="media-item__meta">
                                    <span class="media-item__size">{{ item.size_human }}</span>
                                    <span v-if="item.source" class="media-item__source-tag">来源: {{ sourceLabel(item.source) }}</span>
                                </div>
                                <div class="media-item__time">{{ item.created_at || item.modified }}</div>
                            </div>
                            <div class="media-item__actions">
                                <el-switch
                                    :model-value="item.is_public"
                                    active-text="公开"
                                    inactive-text="私密"
                                    size="small"
                                    @change="(val) => togglePublic(item, val)"
                                    style="margin-right:4px;"
                                />
                                <el-button size="small" @click="copyUrl(item)">
                                    <el-icon><CopyDocument /></el-icon>
                                </el-button>
                                <el-button size="small" type="danger" @click="remove(item)">
                                    <el-icon><Delete /></el-icon>
                                </el-button>
                            </div>
                        </div>
                    </div>
                    <app-pagination
                        :total="meta.total"
                        :page="query.page"
                        :per-page="query.per_page"
                        @change="onPageChange"
                    />
                </template>
                <div v-else-if="!loading" style="text-align:center;padding:48px;color:var(--text-muted);">
                    <el-icon style="font-size:40px;margin-bottom:12px;"><Picture /></el-icon>
                    <p>暂无资源</p>
                </div>
            </div>

            <!-- 预览大图 -->
            <el-image-viewer
                v-if="previewVisible"
                :url-list="previewList"
                :initial-index="previewIndex"
                @close="previewVisible = false"
            />
        </div>
    `,
    setup() {
        const { ref, reactive, onMounted } = Vue
        const loading = ref(false)
        const uploading = ref(false)
        const items = ref([])
        const meta = ref({ total: 0, current_page: 1, per_page: 48, last_page: 1 })
        const query = reactive({ page: 1, per_page: 48 })
        const filters = reactive({ source: '', is_public: '', keyword: '' })

        const previewVisible = ref(false)
        const previewList = ref([])
        const previewIndex = ref(0)

        const editingId = ref(null)
        const editForm = reactive({ file_name: '' })

        const sourceOptions = [
            { value: 'gallery', label: '图库' },
            { value: 'post', label: '文章' },
            { value: 'settings', label: '系统设置' },
            { value: 'editor', label: '编辑器' },
        ]

        function sourceLabel(val) {
            const found = sourceOptions.find(s => s.value === val)
            return found ? found.label : val
        }

        async function loadItems() {
            loading.value = true
            try {
                const params = {
                    page: query.page,
                    per_page: query.per_page,
                }
                if (filters.source) params.source = filters.source
                if (filters.is_public !== '' && filters.is_public !== null) params.is_public = filters.is_public
                if (filters.keyword) params.keyword = filters.keyword
                const res = await AdminApi.get('/media', params)
                items.value = res.data || []
                if (res.meta) meta.value = { ...meta.value, ...res.meta }
            } finally {
                loading.value = false
            }
        }

        function onPageChange({ page, perPage }) {
            query.page = page
            query.per_page = perPage
            loadItems()
        }

        async function uploadFile({ file }) {
            uploading.value = true
            try {
                const fd = new FormData()
                fd.append('file', file)
                await AdminApi.upload('/upload', fd)
                ElementPlus.ElMessage.success('上传成功')
                loadItems()
            } catch (e) {
                // 错误已在 api.js 中处理
            } finally {
                uploading.value = false
            }
        }

        function startEdit(item) {
            editingId.value = item.path
            editForm.file_name = item.file_name || ''
        }

        async function saveEdit(item) {
            const newName = editForm.file_name.trim()
            if (!newName) {
                ElementPlus.ElMessage.warning('文件名不能为空')
                return
            }
            editingId.value = null
            try {
                await AdminApi.put('/media/' + encodeURIComponent(item.path), {
                    file_name: newName,
                })
                ElementPlus.ElMessage.success('已更新')
                loadItems()
            } catch (e) {
                // 错误已在 api.js 中处理
            }
        }

        async function togglePublic(item, val) {
            try {
                await AdminApi.put('/media/' + encodeURIComponent(item.path), {
                    is_public: val ? 1 : 0,
                })
                ElementPlus.ElMessage.success(val ? '已设为公开' : '已设为私密')
                item.is_public = !!val
            } catch (e) {
                // 错误已在 api.js 中处理
            }
        }

        function preview(item) {
            const allImgs = items.value.map(i => i.url || '/' + i.path)
            const idx = allImgs.indexOf(item.url || '/' + item.path)
            previewList.value = allImgs.map(u => {
                const match = items.value.find(i => i.url === u || '/' + i.path === u)
                return match?.webp_url || match?.url || u
            })
            previewIndex.value = idx >= 0 ? idx : 0
            previewVisible.value = true
        }

        function copyUrl(item) {
            const url = item.webp_url || item.url
            if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.origin + url)
                ElementPlus.ElMessage.success('链接已复制')
            } else {
                ElementPlus.ElMessage.info(url)
            }
        }

        async function remove(item) {
            try {
                await ElementPlus.ElMessageBox.confirm(
                    `确定删除 ${item.file_name || item.path}？关联的 WebP 和原图将一并删除。`,
                    '确认删除',
                    { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' }
                )
            } catch (_) { return }
            try {
                await AdminApi.delete('/media/' + encodeURIComponent(item.path))
                ElementPlus.ElMessage.success('已删除')
                loadItems()
            } catch (e) {
                ElementPlus.ElMessage.error('删除失败')
            }
        }

        onMounted(loadItems)
        return {
            loading, uploading, items, meta, query, filters,
            previewVisible, previewList, previewIndex,
            editingId, editForm, sourceOptions, sourceLabel,
            loadItems, onPageChange, uploadFile,
            startEdit, saveEdit, togglePublic,
            preview, copyUrl, remove,
        }
    },
}
