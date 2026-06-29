/**
 * Starter 主题 - 主入口
 */
;(function () {
  'use strict'

  let siteInfo = {}
  let features = {}
  let statusTimer = null
  let galleryPage = 1
  let galleryTotal = 0
  let galleryCategory = ''
  let newsPage = 1
  let newsTotal = 0
  let newsCategory = ''

  // ==================== 初始化 ====================

  async function init() {
    McApi.setBaseURL(CONFIG.API_BASE)
    initParticles()
    initNavbar()
    await loadSiteInfo()
    loadServerStatus()
    startStatusRefresh()
    loadChart()
    loadGallery()
    loadNews()
    loadComments()
    initCommentForm()
    initWhitelistForm()
    loadFriendLinks()
    initScrollAnimations()
  }

  // ==================== 站点信息 ====================

  async function loadSiteInfo() {
    try {
      const res = await McApi.get('/site/info')
      siteInfo = res.data.settings || {}
      features = res.data.features || {}

      const ts = res.data.theme_settings || {}
      const cs = res.data.content_settings || {}
      const serverList = res.data.servers || []

      const g = (key) => ts[key] || siteInfo[key] || cs[key] || ''

      const siteName = siteInfo.site_name || 'MC Server'
      document.title = siteName
      const logo = document.getElementById('nav-logo')
      if (logo) logo.textContent = siteName
      try { localStorage.setItem('mc_site_name', siteName) } catch(e) {}

      const heroTitle = document.getElementById('hero-title')
      if (heroTitle) heroTitle.textContent = g('hero_title') || siteInfo.site_description || '欢迎来到服务器'

      const heroSubtitle = document.getElementById('hero-subtitle')
      // hero-subtitle 已在新的 HTML 结构中移除，保留 JS 引用以防其他主题使用

      const addr = document.getElementById('server-address')
      if (addr) addr.textContent = g('server_address_display')

      const footerBrand = document.getElementById('footer-text')
      if (footerBrand) footerBrand.textContent = siteName
      const footerCopy = document.getElementById('footer-copyright')
      const customCopyright = g('footer_copyright')
      if (footerCopy) {
        footerCopy.textContent = customCopyright || `© ${new Date().getFullYear()} All rights reserved.`
      }

      const icpHtml = g('icp_html')
      const icp = document.getElementById('footer-icp')
      if (icp && icpHtml) {
        icp.innerHTML = icpHtml
      }

      const policeHtml = g('public_security_html')
      const police = document.getElementById('footer-police')
      if (police && policeHtml) {
        police.innerHTML = policeHtml
      }

      const faviconUrl = g('favicon_url')
      if (faviconUrl) {
        let link = document.querySelector('link[rel="icon"]')
        if (!link) { link = document.createElement('link'); link.rel = 'icon'; document.head.appendChild(link) }
        link.href = faviconUrl
      }

      const socialEl = document.getElementById('footer-social')
      if (socialEl) {
        const qqName = g('qq_group_name') || 'QQ 群'
        const qqLink = g('qq_group_link')
        const kookName = g('discord_name') || 'Kook'
        const kookLink = g('discord_link')
        if (qqLink || kookLink) {
          let html = '<div class="footer-col-title">社交</div><div class="footer-col-links">'
          if (qqLink) html += `<a class="footer-col-link" href="${escapeHtml(qqLink)}" target="_blank" rel="noopener">${escapeHtml(qqName)}</a>`
          if (kookLink) html += `<a class="footer-col-link" href="${escapeHtml(kookLink)}" target="_blank" rel="noopener">${escapeHtml(kookName)}</a>`
          html += '</div>'
          socialEl.innerHTML = html
        }
      }

      // 自定义联系方式
      const contactsEl = document.getElementById('footer-contacts')
      if (contactsEl) {
        const raw = g('custom_contacts')
        if (raw) {
          const lines = raw.split('\n').filter(Boolean)
          const items = []
          lines.forEach((line) => {
            const sep = line.indexOf('|')
            if (sep > 0) {
              const name = line.slice(0, sep).trim()
              const link = line.slice(sep + 1).trim()
              if (name && link) items.push({ name, link })
            }
          })
          if (items.length > 0) {
            let html = '<div class="footer-col-title">更多</div><div class="footer-col-links">'
            items.forEach((item) => {
              html += `<a class="footer-col-link" href="${escapeHtml(item.link)}" target="_blank" rel="noopener">${escapeHtml(item.name)}</a>`
            })
            html += '</div>'
            contactsEl.innerHTML = html
          }
        }
      }

      // 页脚自定义 HTML
      const footerCustom = document.getElementById('footer-custom-html')
      if (footerCustom) {
        const customHtml = g('footer_custom_html')
        if (customHtml) {
          footerCustom.innerHTML = customHtml
        }
      }

      // 区域标题（内容配置）
      setSectionTitle('section-servers-title', g('section_servers_title'), '服务器状态')
      setSectionTitle('section-gallery-title', g('section_gallery_title'), '服务器图集')
      setSectionTitle('section-news-title', g('section_news_title'), '服务器动态')
      setSectionTitle('section-comments-title', g('section_comments_title'), '留言板')

      // 功能开关
      toggleSection('gallery', features.gallery)
      toggleSection('comments', features.comment)
      toggleSection('whitelist', features.whitelist)
      if (features.player_chart) {
        document.getElementById('chart-section').style.display = 'block'
      }

      // 主题设置
      applyThemeSettings(res.data.theme_settings || {}, res.data.content_settings || {})
    } catch (e) {
      console.error('加载站点信息失败', e)
    }
  }

  function applyThemeSettings(ts, cs = {}) {
    const root = document.documentElement
    const hero = document.querySelector('.hero')
    const heroBg = document.querySelector('.hero-bg')
    const navbar = document.getElementById('navbar')

    // Hero 始终为深色主题
    if (navbar) navbar.classList.add('navbar--dark')

    const heroImg = typeof ts.hero_image === 'string' ? ts.hero_image.trim() : ''

    document.querySelectorAll('.hero-overlay-layer').forEach(el => el.remove())

    if (heroImg) {
      // 自定义背景图片模式
      const imgUrl = heroImg.startsWith('http') ? heroImg : '/' + heroImg.replace(/^\//, '')
      if (heroBg) heroBg.style.background = `url("${imgUrl}") center/cover no-repeat`
      const overlay = ts.hero_overlay || 'dark'
      if (overlay !== 'none') {
        const overlayEl = document.createElement('div')
        overlayEl.className = 'hero-overlay-layer'
        overlayEl.style.cssText = 'position:absolute;inset:0;z-index:1;'
        overlayEl.style.background = overlay === 'dark' ? 'rgba(0,0,0,0.55)' : 'rgba(255,255,255,0.7)'
        heroBg.parentElement.insertBefore(overlayEl, heroBg.nextSibling)
      }
    }
    // 否则使用默认深色渐变（CSS 中已定义）

    if (ts.card_radius) {
      root.style.setProperty('--radius', ts.card_radius + 'px')
    }

    if (ts.custom_css) {
      const style = document.createElement('style')
      style.textContent = ts.custom_css
      document.head.appendChild(style)
    }

    const customHead = cs.custom_head_html || siteInfo.custom_head_html || ts.custom_head_html || ''
    if (customHead) {
      const div = document.createElement('div')
      div.innerHTML = customHead
      while (div.firstChild) {
        document.head.appendChild(div.firstChild)
      }
    }

    const customCss = cs.custom_css || ts.custom_css || ''
    if (customCss && customCss !== ts.custom_css) {
      const style = document.createElement('style')
      style.textContent = customCss
      document.head.appendChild(style)
    }

    try { localStorage.setItem('mc_theme_cache', JSON.stringify(ts)) } catch(e) {}

    const heroBgEl = document.querySelector('.hero-bg')
    const navbarEl = document.getElementById('navbar')
    if (heroBgEl && !heroBgEl.classList.contains('reveal')) {
      requestAnimationFrame(() => {
        heroBgEl.classList.add('reveal')
        if (navbarEl) navbarEl.classList.add('reveal')
      })
    }
    const preload = document.getElementById('theme-preload')
    if (preload) setTimeout(() => preload.remove(), 1000)
  }

  function toggleSection(id, enabled) {
    const el = document.getElementById(id)
    if (el) el.style.display = enabled ? '' : 'none'
    const navLink = document.querySelector(`.nav-links a[href="#${id}"]`)
    if (navLink) navLink.style.display = enabled ? '' : 'none'
  }

  // ==================== 服务器状态 ====================

  async function loadServerStatus() {
    try {
      const res = await McApi.get('/server/status')
      const d = res.data

      document.getElementById('hero-online').textContent = d.online_players
      document.getElementById('hero-max').textContent = d.max_players
      const motdEl = document.getElementById('hero-motd')
      if (d.motd && d.motd.includes('§')) {
        motdEl.innerHTML = McUtils.parseMotd(d.motd)
      } else {
        motdEl.textContent = d.motd || '欢迎加入服务器'
      }

      const dot = document.querySelector('.status-dot')
      const text = document.getElementById('hero-status-text')
      if (d.is_online) {
        if (dot) dot.style.background = '#10b981'
        text.textContent = '在线'
      } else {
        if (dot) dot.style.background = '#ef4444'
        text.textContent = '离线'
      }

      const heroContent = document.querySelector('.hero-content')
      if (heroContent && !heroContent.classList.contains('ready')) {
        heroContent.classList.add('ready')
      }

      document.getElementById('stat-online').textContent = `${d.online_players} / ${d.max_players}`
      document.getElementById('stat-version').textContent = d.version || '-'
      document.getElementById('stat-latency').textContent = d.latency_ms ? `${d.latency_ms}ms` : '-'
      document.getElementById('stat-querytime').textContent = d.query_time
        ? McUtils.formatDate(d.query_time, 'HH:mm:ss')
        : '-'

      // 玩家列表
      const plSection = document.getElementById('player-list-section')
      const plContainer = document.getElementById('player-list')
      if (d.player_list && d.player_list.length > 0 && features.player_list) {
        plSection.style.display = 'block'
        plContainer.innerHTML = d.player_list
          .map((name) => `<span class="player-tag">${escapeHtml(name)}</span>`)
          .join('')
      } else {
        plSection.style.display = 'none'
      }
    } catch (e) {
      console.error('加载状态失败', e)
    }
  }

  function startStatusRefresh() {
    if (statusTimer) clearInterval(statusTimer)
    statusTimer = setInterval(loadServerStatus, CONFIG.REFRESH_INTERVAL)
  }

  // ==================== 24h 图表 ====================

  async function loadChart() {
    if (!features.player_chart) return
    try {
      const res = await McApi.get('/server/stats/24h')
      const points = res.data || []
      if (points.length === 0) return

      const chartDom = document.getElementById('player-chart')
      const chart = echarts.init(chartDom)

      chart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis' },
        grid: { left: 50, right: 20, top: 20, bottom: 40 },
        xAxis: {
          type: 'category',
          data: points.map((p) => {
            const d = new Date(p.time)
            return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
          }),
          axisLabel: { interval: 'auto', rotate: 0, color: '#999' },
          axisLine: { lineStyle: { color: 'rgba(0,0,0,0.06)' } },
        },
        yAxis: { type: 'value', minInterval: 1, splitLine: { lineStyle: { color: 'rgba(0,0,0,0.04)' } }, axisLabel: { color: '#999' } },
        series: [
          {
            name: '平均在线',
            type: 'line',
            data: points.map((p) => p.avg_players),
            smooth: true,
            areaStyle: { color: { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [{ offset: 0, color: 'rgba(17,17,17,0.08)' }, { offset: 1, color: 'transparent' }] } },
            lineStyle: { width: 2, color: '#111' },
            itemStyle: { color: '#111' },
          },
        ],
      })

      window.addEventListener('resize', () => chart.resize())
    } catch (e) {
      console.error('加载图表失败', e)
    }
  }

  // ==================== 图集 ====================

  async function loadGallery(append = false) {
    if (!features.gallery) return
    try {
      const res = await McApi.get('/gallery', {
        page: galleryPage,
        per_page: 12,
        category_id: galleryCategory || undefined,
      })

      const items = res.data || []
      galleryTotal = res.meta?.total || 0
      const grid = document.getElementById('gallery-grid')

      if (!append) grid.innerHTML = ''

      items.forEach((img) => {
        const div = document.createElement('div')
        div.className = 'gallery-item'
        const src = McUtils.getWebpUrl(img.thumb_path || img.file_path)
        const fullSrc = McUtils.getWebpUrl(img.file_path)
        div.innerHTML = `
          <img src="${src}" alt="${escapeHtml(img.title || '')}" loading="lazy" data-full="${fullSrc}" />
          ${img.title ? `<div class="gallery-item-title">${escapeHtml(img.title)}</div>` : ''}
        `
        div.addEventListener('click', () => {
          const allImgs = Array.from(grid.querySelectorAll('img')).map((i) => i.dataset.full)
          const idx = allImgs.indexOf(fullSrc)
          McLightbox.open(allImgs, idx >= 0 ? idx : 0)
        })
        grid.appendChild(div)
      })

      const moreBtn = document.getElementById('gallery-more')
      moreBtn.style.display = galleryPage * 12 < galleryTotal ? 'block' : 'none'

      // 加载分类
      if (!append) loadGalleryCategories()
    } catch (e) {
      console.error('加载图集失败', e)
    }
  }

  async function loadGalleryCategories() {
    try {
      const res = await McApi.get('/gallery/categories')
      const cats = res.data || []
      const container = document.getElementById('gallery-categories')

      let html = `<button class="cat-btn ${!galleryCategory ? 'active' : ''}" data-cat="">全部</button>`
      cats.forEach((c) => {
        html += `<button class="cat-btn ${galleryCategory == c.id ? 'active' : ''}" data-cat="${c.id}">${escapeHtml(c.name)}</button>`
      })
      container.innerHTML = html

      container.querySelectorAll('.cat-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          galleryCategory = btn.dataset.cat
          galleryPage = 1
          loadGallery()
        })
      })
    } catch (e) { /* ignore */ }
  }

  // ==================== 动态 ====================

  async function loadNews(append = false) {
    try {
      const params = { page: newsPage, per_page: 6 }
      if (newsCategory) params.category_id = newsCategory
      const res = await McApi.get('/posts', params)
      const items = res.data || []
      newsTotal = res.meta?.total || 0
      const list = document.getElementById('news-list')

      if (!append) list.innerHTML = ''

      if (items.length === 0 && !append) {
        list.innerHTML = '<p class="empty-text">暂无动态</p>'
        return
      }

      items.forEach((post) => {
        const div = document.createElement('div')
        div.className = 'news-card'
        const summary = McUtils.truncate(McUtils.stripHtml(post.content), 120)
        div.innerHTML = `
          ${post.cover_image ? `<div class="news-cover"><img src="${McUtils.getWebpUrl(post.cover_image)}" alt="" loading="lazy" /></div>` : ''}
          <div class="news-body">
            <h3 class="news-title">${post.is_pinned ? '<span class="pin-tag">置顶</span>' : ''}${escapeHtml(post.title)}</h3>
            <p class="news-summary">${escapeHtml(summary)}</p>
            <div class="news-meta">
              ${post.category?.name ? `<span class="post-cat-tag">${escapeHtml(post.category.name)}</span>` : ''}
              <span>${post.author?.nickname || ''}</span>
              <span>${McUtils.formatDate(post.published_at)}</span>
            </div>
          </div>
        `
        div.addEventListener('click', () => showPostDetail(post.id))
        list.appendChild(div)
      })

      const moreBtn = document.getElementById('news-more')
      moreBtn.style.display = newsPage * 6 < newsTotal ? 'block' : 'none'

      if (!append) loadNewsCategories()
    } catch (e) {
      console.error('加载动态失败', e)
    }
  }

  async function loadNewsCategories() {
    try {
      const res = await McApi.get('/posts/categories')
      const cats = res.data || []
      const container = document.getElementById('news-categories')
      if (!container || cats.length === 0) {
        if (container) container.style.display = 'none'
        return
      }
      container.style.display = ''

      let html = `<button class="cat-btn ${!newsCategory ? 'active' : ''}" data-cat="">全部</button>`
      cats.forEach((c) => {
        html += `<button class="cat-btn ${newsCategory == c.id ? 'active' : ''}" data-cat="${c.id}">${escapeHtml(c.name)}</button>`
      })
      container.innerHTML = html

      container.querySelectorAll('.cat-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          newsCategory = btn.dataset.cat
          newsPage = 1
          loadNews()
        })
      })
    } catch (e) { /* ignore */ }
  }

  async function showPostDetail(id) {
    try {
      const res = await McApi.get(`/posts/${id}`)
      const post = res.data
      document.getElementById('post-detail-title').textContent = post.title
      const catName = post.category?.name || ''
      document.getElementById('post-detail-meta').innerHTML = `
        ${catName ? `<span class="post-cat-tag">${escapeHtml(catName)}</span>` : ''}
        <span>${post.author?.nickname || ''}</span> · <span>${McUtils.formatDate(post.published_at)}</span>
      `
      document.getElementById('post-detail-body').innerHTML = post.content
      const scroll = document.querySelector('.modal-scroll')
      if (scroll) scroll.scrollTop = 0
      openModal('post-modal')
    } catch (e) {
      McToast.error('加载失败')
    }
  }

  function openModal(id) {
    const modal = document.getElementById(id)
    if (!modal) return
    modal.style.display = 'flex'
    modal.classList.remove('modal--closing')
    document.body.style.overflow = 'hidden'
    requestAnimationFrame(() => {
      requestAnimationFrame(() => modal.classList.add('modal--visible'))
    })
  }

  function closeModal(id) {
    const modal = document.getElementById(id)
    if (!modal || !modal.classList.contains('modal--visible')) return
    modal.classList.add('modal--closing')
    modal.classList.remove('modal--visible')
    let done = false
    const onEnd = () => {
      if (done) return
      done = true
      modal.removeEventListener('transitionend', onEnd)
      modal.style.display = 'none'
      modal.classList.remove('modal--closing')
      document.body.style.overflow = ''
    }
    modal.addEventListener('transitionend', onEnd)
    setTimeout(onEnd, 350)
  }

  // ==================== 留言 ====================

  async function loadComments() {
    if (!features.comment) return
    try {
      const res = await McApi.get('/comments', { per_page: 50 })
      const items = res.data || []
      const track = document.getElementById('comment-list')
      const marquee = document.getElementById('comment-marquee')

      if (items.length === 0) {
        if (marquee) marquee.innerHTML = '<p class="empty-text" style="width:100%;text-align:center">暂无留言，快来留下第一条吧</p>'
        return
      }

      function buildCard(c) {
        return `<div class="comment-item">
          <div class="comment-item-top">
            <div class="comment-avatar">${c.nickname.charAt(0).toUpperCase()}</div>
            <div class="comment-meta">
              <div class="comment-name">${escapeHtml(c.nickname)}</div>
              <div class="comment-time">${McUtils.formatDate(c.created_at)}</div>
            </div>
          </div>
          <p class="comment-text">${escapeHtml(c.content)}</p>
          ${c.admin_reply ? `<div class="comment-reply"><strong>管理员回复：</strong>${escapeHtml(c.admin_reply)}</div>` : ''}
        </div>`
      }

      const cardsHtml = items.map(buildCard).join('')
      track.innerHTML = cardsHtml + cardsHtml

      const cardCount = items.length
      const speed = Math.max(20, cardCount * 4)
      track.style.animationDuration = speed + 's'
    } catch (e) {
      console.error('加载留言失败', e)
    }
  }

  function initCommentForm() {
    const form = document.getElementById('comment-form')
    if (!form) return

    const tsEl = document.getElementById('comment-ts')
    if (tsEl) tsEl.value = String(Date.now())

    form.addEventListener('submit', async (e) => {
      e.preventDefault()
      const nickname = document.getElementById('comment-nickname').value.trim()
      const email = document.getElementById('comment-email').value.trim()
      const content = document.getElementById('comment-content').value.trim()
      const hp = document.getElementById('comment-hp')?.value || ''
      const ts = document.getElementById('comment-ts')?.value || ''

      if (!nickname || !content) {
        McToast.warning('请填写昵称和留言内容')
        return
      }

      try {
        await McApi.post('/comments', { nickname, email, content, _hp: hp, _ts: ts })
        McToast.success('留言提交成功，等待审核')
        form.reset()
        if (tsEl) tsEl.value = String(Date.now())
      } catch (e) {
        McToast.error(e.message || '提交失败')
      }
    })
  }

  // ==================== 白名单 ====================

  function initWhitelistForm() {
    const form = document.getElementById('whitelist-form')
    if (!form) return

    const wlTsEl = document.getElementById('wl-ts')
    if (wlTsEl) wlTsEl.value = String(Date.now())

    form.addEventListener('submit', async (e) => {
      e.preventDefault()
      const player_name = document.getElementById('wl-player-name').value.trim()
      const platform = document.getElementById('wl-platform').value
      const contact = document.getElementById('wl-contact').value.trim()
      const reason = document.getElementById('wl-reason').value.trim()
      const hp = document.getElementById('wl-hp')?.value || ''
      const ts = document.getElementById('wl-ts')?.value || ''

      if (!player_name) {
        McToast.warning('请填写游戏ID')
        return
      }

      try {
        await McApi.post('/whitelist/apply', { player_name, platform, contact, reason, _hp: hp, _ts: ts })
        McToast.success('申请已提交，等待审核')
        form.reset()
        if (wlTsEl) wlTsEl.value = String(Date.now())
      } catch (e) {
        McToast.error(e.message || '提交失败')
      }
    })

    document.getElementById('wl-check-btn')?.addEventListener('click', async () => {
      const name = document.getElementById('wl-check-name').value.trim()
      if (!name) return McToast.warning('请输入游戏ID')

      try {
        const res = await McApi.get(`/whitelist/check/${encodeURIComponent(name)}`)
        const d = res.data
        const resultDiv = document.getElementById('wl-check-result')

        const statusMap = {
          pending: { text: '待审核', cls: 'status-pending' },
          approved: { text: '已通过', cls: 'status-approved' },
          rejected: { text: '已拒绝', cls: 'status-rejected' },
          not_found: { text: '未找到申请记录', cls: 'status-notfound' },
        }
        const s = statusMap[d.status] || statusMap.not_found
        resultDiv.innerHTML = `
          <div class="check-result ${s.cls}">
            <span>状态：${s.text}</span>
            ${d.admin_note ? `<p>备注：${escapeHtml(d.admin_note)}</p>` : ''}
            ${d.created_at ? `<p>提交时间：${McUtils.formatDate(d.created_at)}</p>` : ''}
          </div>
        `
      } catch (e) {
        McToast.error('查询失败')
      }
    })
  }

  // ==================== 导航栏 ====================

  function initNavbar() {
    const toggle = document.getElementById('nav-toggle')
    const links = document.querySelector('.nav-links')

    toggle?.addEventListener('click', () => {
      links.classList.toggle('active')
      toggle.classList.toggle('active')
    })

    document.querySelectorAll('.nav-links a').forEach((a) => {
      a.addEventListener('click', () => {
        links.classList.remove('active')
        toggle.classList.remove('active')
      })
    })

    // 滚动固定导航
    window.addEventListener('scroll', () => {
      const navbar = document.getElementById('navbar')
      navbar.classList.toggle('scrolled', window.scrollY > 60)
    })

    // 复制按钮
    document.getElementById('copy-btn')?.addEventListener('click', () => {
      const addr = document.getElementById('server-address').textContent
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(addr).then(() => McToast.success('地址已复制'))
      } else {
        const ta = document.createElement('textarea')
        ta.value = addr
        ta.style.position = 'fixed'
        ta.style.opacity = '0'
        document.body.appendChild(ta)
        ta.select()
        document.execCommand('copy')
        document.body.removeChild(ta)
        McToast.success('地址已复制')
      }
    })

    // 动态详情弹窗关闭
    document.getElementById('post-modal-close')?.addEventListener('click', closePostModal)
    document.querySelector('#post-modal .modal-overlay')?.addEventListener('click', closePostModal)

    // 加载更多
    document.getElementById('gallery-load-more')?.addEventListener('click', () => {
      galleryPage++
      loadGallery(true)
    })
    document.getElementById('news-load-more')?.addEventListener('click', () => {
      newsPage++
      loadNews(true)
    })

    // 滚动指示器 — 点击滚到第二屏
    document.getElementById('hero-scroll')?.addEventListener('click', () => {
      const statusSection = document.getElementById('status')
      if (statusSection) {
        statusSection.scrollIntoView({ behavior: 'smooth' })
      }
    })
  }

  function closePostModal() {
    closeModal('post-modal')
  }

  // ==================== 滚动动画 ====================

  function initScrollAnimations() {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible')
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.1 }
    )

    document.querySelectorAll('.fade-up').forEach((el) => observer.observe(el))
  }

  // ==================== 友情链接 ====================

  async function loadFriendLinks() {
    try {
      const res = await McApi.get('/friend-links')
      const links = res.data || []
      const container = document.getElementById('footer-links')
      if (!container || links.length === 0) return
      let html = '<div class="footer-col-title">友情链接</div><div class="footer-col-links">'
      links.forEach((link) => {
        html += `<a class="footer-col-link" href="${escapeHtml(link.url)}" target="_blank" rel="noopener" title="${escapeHtml(link.description || '')}">${escapeHtml(link.name)}</a>`
      })
      html += '</div>'
      container.innerHTML = html
    } catch (e) { /* ignore */ }
  }

  // ==================== 粒子背景 ====================

  function initParticles() {
    const canvas = document.getElementById('hero-particles')
    if (!canvas) return
    const ctx = canvas.getContext('2d')
    let particles = []
    let animId = null

    function resize() {
      canvas.width = canvas.offsetWidth * (window.devicePixelRatio || 1)
      canvas.height = canvas.offsetHeight * (window.devicePixelRatio || 1)
      ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1)
    }

    function createParticles() {
      const count = Math.min(60, Math.floor((canvas.offsetWidth * canvas.offsetHeight) / 15000))
      particles = []
      for (let i = 0; i < count; i++) {
        particles.push({
          x: Math.random() * canvas.offsetWidth,
          y: Math.random() * canvas.offsetHeight,
          r: Math.random() * 1.5 + 0.5,
          vx: (Math.random() - 0.5) * 0.3,
          vy: (Math.random() - 0.5) * 0.3,
          opacity: Math.random() * 0.5 + 0.1,
        })
      }
    }

    function draw() {
      ctx.clearRect(0, 0, canvas.offsetWidth, canvas.offsetHeight)
      particles.forEach((p) => {
        p.x += p.vx
        p.y += p.vy
        if (p.x < 0) p.x = canvas.offsetWidth
        if (p.x > canvas.offsetWidth) p.x = 0
        if (p.y < 0) p.y = canvas.offsetHeight
        if (p.y > canvas.offsetHeight) p.y = 0

        ctx.beginPath()
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2)
        ctx.fillStyle = `rgba(255,255,255,${p.opacity})`
        ctx.fill()
      })

      // 连线
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const dx = particles[i].x - particles[j].x
          const dy = particles[i].y - particles[j].y
          const dist = Math.sqrt(dx * dx + dy * dy)
          if (dist < 120) {
            ctx.beginPath()
            ctx.moveTo(particles[i].x, particles[i].y)
            ctx.lineTo(particles[j].x, particles[j].y)
            ctx.strokeStyle = `rgba(255,255,255,${0.04 * (1 - dist / 120)})`
            ctx.lineWidth = 0.5
            ctx.stroke()
          }
        }
      }

      animId = requestAnimationFrame(draw)
    }

    resize()
    createParticles()
    draw()

    window.addEventListener('resize', () => {
      resize()
      createParticles()
    })
  }

  // ==================== 工具 ====================

  function setSectionTitle(id, value, fallback) {
    const el = document.getElementById(id)
    if (el && value) el.textContent = value
    else if (el && fallback) el.textContent = fallback
  }

  function escapeHtml(str) {
    if (!str) return ''
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }
    return str.replace(/[&<>"']/g, (c) => map[c])
  }

  // ==================== 启动 ====================

  document.addEventListener('DOMContentLoaded', init)
})()
