/**
 * 全局工具函数
 */
const McUtils = (() => {

  function formatDate(dateStr, format = 'YYYY-MM-DD HH:mm') {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    const pad = (n) => String(n).padStart(2, '0')

    const map = {
      YYYY: d.getFullYear(),
      MM: pad(d.getMonth() + 1),
      DD: pad(d.getDate()),
      HH: pad(d.getHours()),
      mm: pad(d.getMinutes()),
      ss: pad(d.getSeconds()),
    }

    let result = format
    for (const [key, val] of Object.entries(map)) {
      result = result.replace(key, val)
    }
    return result
  }

  function truncate(str, len = 80) {
    if (!str) return ''
    return str.length > len ? str.substring(0, len) + '...' : str
  }

  function stripHtml(html) {
    const div = document.createElement('div')
    div.innerHTML = html
    return div.textContent || div.innerText || ''
  }

  function formatNumber(num) {
    if (num === null || num === undefined) return '0'
    return Number(num).toLocaleString()
  }

  function debounce(fn, delay = 300) {
    let timer
    return function (...args) {
      clearTimeout(timer)
      timer = setTimeout(() => fn.apply(this, args), delay)
    }
  }

  function getStorageUrl(path) {
    if (!path) return ''
    if (path.startsWith('http')) return path
    return '/' + path.replace(/^\//, '')
  }

  function getWebpUrl(path) {
    if (!path) return ''
    if (path.startsWith('http')) return path
    // 检查是否有 .webp 版本，上传组件在 upload.php 中会同时生成 .webp
    const webpPath = path.replace(/\.(jpe?g|png|gif)$/i, '.webp')
    if (webpPath !== path) {
      return '/' + webpPath.replace(/^\//, '')
    }
    return '/' + path.replace(/^\//, '')
  }

  const motdColors = {
    '0': '#000000', '1': '#0000AA', '2': '#00AA00', '3': '#00AAAA',
    '4': '#AA0000', '5': '#AA00AA', '6': '#FFAA00', '7': '#AAAAAA',
    '8': '#555555', '9': '#5555FF', 'a': '#55FF55', 'b': '#55FFFF',
    'c': '#FF5555', 'd': '#FF55FF', 'e': '#FFFF55', 'f': '#FFFFFF',
  }

  function parseMotd(raw) {
    if (!raw) return ''
    const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    let html = ''
    let color = null
    let bold = false, italic = false, underline = false, strike = false
    const parts = raw.split(/§/g)
    html += esc(parts[0])
    for (let i = 1; i < parts.length; i++) {
      const code = parts[i][0]?.toLowerCase()
      const text = parts[i].substring(1)
      if (motdColors[code]) {
        color = motdColors[code]
      } else if (code === 'l') {
        bold = true
      } else if (code === 'o') {
        italic = true
      } else if (code === 'n') {
        underline = true
      } else if (code === 'm') {
        strike = true
      } else if (code === 'r') {
        color = null; bold = false; italic = false; underline = false; strike = false
      }
      if (text) {
        const styles = []
        if (color) styles.push('color:' + color)
        if (bold) styles.push('font-weight:700')
        if (italic) styles.push('font-style:italic')
        if (underline) styles.push('text-decoration:underline')
        if (strike) styles.push('text-decoration:line-through')
        if (styles.length) {
          html += '<span style="' + styles.join(';') + '">' + esc(text) + '</span>'
        } else {
          html += esc(text)
        }
      }
    }
    return html
  }

  return { formatDate, truncate, stripHtml, formatNumber, debounce, getStorageUrl, getWebpUrl, parseMotd }
})()

if (typeof window !== 'undefined') {
  window.McUtils = McUtils
}
