/**
 * 资源管理 — 浏览/删除已上传的资源
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

            <div class="card-box" v-loading="loading">
                <template v-if="items.length > 0">
                    <div class="media-grid">
                        <div v-for="item in items" :key="item.path" class="media-item">
                            <div class="media-item__preview" @click="preview(item)">
                                <img :src="item.webp_url || item.url" :alt="item.path" loading="lazy" />
                                <div class="media-item__overlay">
                                    <el-icon style="font-size:32px;color:#fff;"><View /></el-icon>
                                </div>
                            </div>
                            <div class="media-item__info">
                                <div class="media-item__size">{{ item.size_human }}</div>
                                <div class="media-item__time">{{ item.modified }}</div>
                            </div>
                            <div class="media-item__actions">
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
                    <p>暂无上传的资源</p>
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

        const previewVisible = ref(false)
        const previewList = ref([])
        const previewIndex = ref(0)

        async function loadItems() {
            loading.value = true
            try {
                const res = await AdminApi.get('/media', {
                    page: query.page,
                    per_page: query.per_page,
                })
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
                const res = await AdminApi.upload('/upload', fd)
                ElementPlus.ElMessage.success('上传成功')
                loadItems()
            } catch (e) {
                // 错误已在 api.js 中处理
            } finally {
                uploading.value = false
            }
        }

        function preview(item) {
            const allImgs = items.value.map(i => i.url || '/' + i.path)
            const idx = allImgs.indexOf(item.url || '/' + item.path)
            previewList.value = allImgs.map(u => {
                // 如果有 webp 用 webp 预览
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
                    `确定删除文件 ${item.path}？关联的 WebP 和原图将一并删除。`,
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
            loading, uploading, items, meta, query,
            previewVisible, previewList, previewIndex,
            loadItems, onPageChange, uploadFile, preview, copyUrl, remove,
        }
    },
}
