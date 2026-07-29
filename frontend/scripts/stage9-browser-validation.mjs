#!/usr/bin/env node

import { spawn } from 'node:child_process'
import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import { createServer } from 'node:net'
import { tmpdir } from 'node:os'
import { resolve, join } from 'node:path'
import process from 'node:process'

const BROWSERS = [
  ['chrome', String.raw`C:\Program Files\Google\Chrome\Application\chrome.exe`],
  ['edge', String.raw`C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`],
]
const VIEWPORTS = [
  [1440, 900],
  [1366, 768],
  [768, 1024],
  [390, 844],
  [360, 800],
]
const WARNING =
  'Peringatan self-approval Anda juga tercatat sebagai PIC utama tiket ini. Approval akan dicatat sebagai self-approval pada audit trail.'
const options = parseArgs(process.argv.slice(2))
const frontend = (options['frontend-url'] || process.env.STAGE9_FRONTEND_URL || 'http://localhost:5173').replace(
  /\/$/,
  '',
)
const backend = (options['backend-url'] || process.env.STAGE9_BACKEND_URL || 'http://localhost:8000').replace(/\/$/, '')
const evidence = resolve(
  options['evidence-dir'] || process.env.STAGE9_EVIDENCE_DIR || join(tmpdir(), 'stage9-browser-evidence'),
)
const timeout = Number(options.timeout || process.env.STAGE9_TIMEOUT_MS || 20_000)
const password = process.env.STAGE9_BROWSER_PASSWORD || ''

if (!options.manifest) usage('Pass --manifest=<fixture-manifest.json>.')
if (password.length < 16) usage('STAGE9_BROWSER_PASSWORD must contain at least 16 characters.')
if (![frontend, backend].every((url) => /^https?:\/\//.test(url)))
  usage('Frontend and backend URLs must be HTTP(S) URLs.')
const manifest = JSON.parse((await readFile(resolve(options.manifest), 'utf8')).replace(/^\uFEFF/, ''))
if (manifest.fixture !== 'stage9-browser') usage('The manifest is not a Stage 9 browser fixture manifest.')
await mkdir(evidence, { recursive: true })

const summary = {
  schema_version: 1,
  started_at: new Date().toISOString(),
  frontend_url: frontend,
  backend_url: backend,
  evidence_directory: evidence,
  viewports: VIEWPORTS.map(([width, height]) => ({ width, height })),
  browsers: [],
  totals: { checks: 0, passed: 0, failed: 0, critical_failed: 0 },
}

for (const [name, fallback] of BROWSERS) {
  const executable = options[name] || process.env[`STAGE9_${name.toUpperCase()}_PATH`] || fallback
  summary.browsers.push(await runBrowser(name, executable))
}
summary.finished_at = new Date().toISOString()
for (const browser of summary.browsers) {
  for (const item of browser.checks) {
    summary.totals.checks += 1
    summary.totals[item.ok ? 'passed' : 'failed'] += 1
    if (!item.ok && item.critical) summary.totals.critical_failed += 1
  }
}
await writeFile(join(evidence, 'summary.json'), `${JSON.stringify(summary, null, 2)}\n`)
console.log(JSON.stringify(summary, null, 2))
process.exitCode = summary.totals.critical_failed ? 1 : 0

async function runBrowser(name, executable) {
  const output = {
    name,
    executable,
    version: null,
    checks: [],
    diagnostics: { console: [], network: [] },
    screenshots: [],
  }
  let child
  let cdp
  let profile
  try {
    await preflight(output, `${frontend}/`, `${name}:frontend-preflight`)
    await preflight(output, `${backend}/api/v1/health`, `${name}:backend-preflight`)
    profile = await mkdtemp(join(tmpdir(), `stage9-${name}-`))
    const port = await freePort()
    child = spawn(
      executable,
      [
        '--headless=new',
        '--disable-background-networking',
        '--disable-component-update',
        '--disable-default-apps',
        '--disable-extensions',
        '--disable-sync',
        '--no-first-run',
        '--no-default-browser-check',
        '--password-store=basic',
        '--remote-allow-origins=*',
        `--remote-debugging-port=${port}`,
        `--user-data-dir=${profile}`,
        'about:blank',
      ],
      { stdio: 'ignore', windowsHide: true },
    )
    const version = await pollJson(`http://127.0.0.1:${port}/json/version`)
    output.version = version.Browser
    const target = await fetch(`http://127.0.0.1:${port}/json/new?about%3Ablank`, { method: 'PUT' })
      .then(ensureOk)
      .then((response) => response.json())
    cdp = new Cdp(target.webSocketDebuggerUrl)
    await cdp.connect()
    await Promise.all(
      ['Page.enable', 'Runtime.enable', 'Network.enable', 'Log.enable'].map((method) => cdp.send(method)),
    )
    diagnostics(cdp, output)

    const scenarios = [
      [
        'requester',
        [
          [
            'requester-list',
            manifest.frontend_paths.requester_list,
            ['Riwayat Tiket Saya', 'Nomor', 'Judul', 'Status'],
          ],
          [
            'requester-create',
            manifest.frontend_paths.requester_create,
            ['Form Pengajuan Tiket', 'Judul Pengajuan Tiket', 'Kirim Tiket'],
          ],
          ['requester-detail', manifest.frontend_paths.requester_detail, ['STAGE9-DYNAMIC-001']],
        ],
      ],
      [
        'supervisor',
        [
          ['supervisor-dashboard', manifest.frontend_paths.supervisor_dashboard, ['Supervisor IT']],
          ['supervisor-list', manifest.frontend_paths.supervisor_list, ['Tiket']],
          [
            'supervisor-detail',
            manifest.frontend_paths.supervisor_self_approval,
            ['STAGE9-DYNAMIC-003', 'Setujui & Selesaikan'],
          ],
          ['legacy-detail', manifest.frontend_paths.legacy_detail, ['STAGE9-LEGACY-001']],
        ],
      ],
      [
        'support',
        [
          ['pic-dashboard', manifest.frontend_paths.pic_dashboard, ['Workspace PIC IT', 'Perlu Tindakan PIC Saat Ini']],
          ['pic-list', manifest.frontend_paths.pic_list, ['Daftar Tiket Penanganan PIC']],
          ['pic-detail', manifest.frontend_paths.pic_detail, ['STAGE9-DYNAMIC-002']],
        ],
      ],
      [
        'admin',
        [
          [
            'workflow-list',
            manifest.frontend_paths.workflow_list,
            ['Workflow Configuration', 'Code', 'Nama', 'Status', 'Aksi'],
          ],
          [
            'workflow-detail',
            manifest.frontend_paths.workflow_detail,
            ['Approval Configuration', 'Transitions', 'supervisor_it'],
          ],
          ['workflow-editor', manifest.frontend_paths.workflow_editor, ['Workflow']],
        ],
      ],
    ]

    for (const [userKey, pages] of scenarios) {
      await login(cdp, output, userKey)
      for (const [width, height] of VIEWPORTS) {
        await cdp.send('Emulation.setDeviceMetricsOverride', {
          width,
          height,
          deviceScaleFactor: 1,
          mobile: width < 600,
        })
        for (const page of pages) await validatePage(cdp, output, name, page, width, height)
      }
    }
    const consoleFailures = output.diagnostics.console.filter(
      (item) => item.type === 'exception' || item.type === 'error',
    )
    record(
      output,
      `${name}:console-exceptions`,
      consoleFailures.length === 0,
      true,
      `${consoleFailures.length} exception/error entries`,
    )
    record(
      output,
      `${name}:network-failures`,
      output.diagnostics.network.length === 0,
      true,
      `${output.diagnostics.network.length} failed/error responses`,
    )
  } catch (error) {
    record(output, `${name}:harness`, false, true, error instanceof Error ? error.message : String(error))
  } finally {
    cdp?.close()
    child?.kill()
    if (profile) await rm(profile, { recursive: true, force: true }).catch(() => {})
  }
  return output
}

async function login(cdp, output, userKey) {
  await cdp.send('Network.clearBrowserCookies')
  await navigate(cdp, `${frontend}/login`)
  await waitFor(cdp, `document.querySelector('input[name=email]')`)
  await evaluate(
    cdp,
    `(() => {
    const set = (selector, value) => {
      const input = document.querySelector(selector)
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value)
      input.dispatchEvent(new Event('input', { bubbles: true }))
    }
    set('input[name=email]', ${JSON.stringify(manifest.users[userKey].email)})
    set('input[name=password]', ${JSON.stringify(password)})
    document.querySelector('form').requestSubmit()
  })()`,
  )
  await waitFor(cdp, `location.pathname !== '/login' && !document.body.innerText.includes('Memeriksa sesi')`)
  record(output, `login:${userKey}`, true, true)
}

async function validatePage(cdp, output, browser, [name, path, expected], width, height) {
  const prefix = `${browser}:${width}x${height}:${name}`
  await navigate(cdp, `${frontend}${path}`)
  await waitFor(
    cdp,
    `document.readyState === 'complete' && !/Memuat (halaman|detail tiket|workflow)/.test(document.body.innerText)`,
  )
  await sleep(250)
  const state = await evaluate(
    cdp,
    `(() => {
    const visible = (element) => { const r = element.getBoundingClientRect(); const s = getComputedStyle(element); return r.width > 0 && r.height > 0 && s.display !== 'none' && s.visibility !== 'hidden' }
    const text = document.body.innerText.replace(/\\s+/g, ' ').trim()
    const unlabeled = [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((field) => visible(field) && !(field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]')) && !field.getAttribute('aria-label') && !field.getAttribute('aria-labelledby') && !field.closest('label')).length
    const emptyButtons = [...document.querySelectorAll('button')].filter((button) => visible(button) && !button.innerText.trim() && !button.getAttribute('aria-label') && !button.getAttribute('title')).length
    const headerlessTables = [...document.querySelectorAll('table')].filter((table) => visible(table) && !table.querySelector('th')).length
    return { text, path: location.pathname, overflow: Math.max(document.body.scrollWidth, document.documentElement.scrollWidth) - innerWidth, unlabeled, emptyButtons, headerlessTables }
  })()`,
  )
  record(output, `${prefix}:route`, !['/login', '/unauthorized'].includes(state.path), true, `landed=${state.path}`)
  record(
    output,
    `${prefix}:content`,
    expected.every((item) => state.text.includes(item)),
    true,
    `missing=${expected.filter((item) => !state.text.includes(item)).join(',')}`,
  )
  record(output, `${prefix}:overflow`, state.overflow <= 1, true, `${state.overflow}px`)
  record(
    output,
    `${prefix}:labels-buttons-headers`,
    state.unlabeled + state.emptyButtons + state.headerlessTables === 0,
    true,
    `unlabeled=${state.unlabeled},emptyButtons=${state.emptyButtons},headerlessTables=${state.headerlessTables}`,
  )
  await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 })
  await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 })
  const focus = await evaluate(
    cdp,
    `(() => { const e = document.activeElement; const r = e?.getBoundingClientRect(); return { tag: e?.tagName, visible: Boolean(r?.width && r?.height) } })()`,
  )
  record(
    output,
    `${prefix}:keyboard-focus`,
    focus.visible && !['BODY', 'HTML'].includes(focus.tag),
    true,
    `focused=${focus.tag}`,
  )
  if (name === 'requester-create') await requesterValidation(cdp, output, prefix)
  if (name === 'supervisor-detail') await selfApproval(cdp, output, prefix)
  const file = clean(`${browser}-${width}x${height}-${name}.png`)
  const shot = await cdp.send('Page.captureScreenshot', {
    format: 'png',
    fromSurface: true,
    captureBeyondViewport: false,
  })
  await writeFile(join(evidence, file), Buffer.from(shot.data, 'base64'))
  output.screenshots.push(file)
}

async function requesterValidation(cdp, output, prefix) {
  await evaluate(cdp, `document.querySelector('form button[type=submit]')?.click()`)
  const validation = await evaluate(
    cdp,
    `document.body.innerText.includes('Judul pengajuan tiket wajib diisi.') && document.body.innerText.includes('Deskripsi pengajuan tiket wajib diisi.')`,
  )
  record(output, `${prefix}:requester-form-validation`, validation, true)
}

async function selfApproval(cdp, output, prefix) {
  await evaluate(
    cdp,
    `(() => [...document.querySelectorAll('button')].find((button) => button.innerText.includes('Setujui & Selesaikan'))?.click())()`,
  )
  await waitFor(cdp, `document.querySelector('[role=dialog]')`)
  const modal = await evaluate(
    cdp,
    `(() => {
    const dialog = document.querySelector('[role=dialog]'); const rect = dialog.getBoundingClientRect()
    const warning = document.querySelector('#self-approval-warning')?.innerText.replace(/\\s+/g, ' ').trim() || ''
    const confirm = [...dialog.querySelectorAll('button')].find((button) => button.innerText.includes('Konfirmasi Aksi'))
    return { warning, disabled: confirm?.disabled === true, visible: rect.top >= 0 && rect.left >= 0 && rect.right <= innerWidth && rect.bottom <= innerHeight, scrollable: dialog.scrollHeight <= innerHeight || ['auto','scroll'].includes(getComputedStyle(dialog.parentElement).overflowY) }
  })()`,
  )
  record(output, `${prefix}:self-approval-warning`, modal.warning === WARNING, true, modal.warning)
  record(output, `${prefix}:self-approval-disabled`, modal.disabled, true)
  record(output, `${prefix}:modal-visible-scrollable`, modal.visible || modal.scrollable, true)
  await evaluate(cdp, `document.querySelector('[role=dialog]')?.querySelector('button')?.click()`)
}

function diagnostics(cdp, output) {
  cdp.on('Runtime.exceptionThrown', (event) =>
    output.diagnostics.console.push({
      type: 'exception',
      text: redact(event.exceptionDetails?.text || ''),
      url: safeUrl(event.exceptionDetails?.url),
    }),
  )
  cdp.on('Log.entryAdded', ({ entry }) => {
    if (['error', 'warning'].includes(entry?.level))
      output.diagnostics.console.push({ type: entry.level, text: redact(entry.text), url: safeUrl(entry.url) })
  })
  cdp.on('Network.loadingFailed', (event) => {
    if (!event.canceled) output.diagnostics.network.push({ type: 'failed', error: redact(event.errorText) })
  })
  cdp.on('Network.responseReceived', ({ response }) => {
    if (response.url.includes('/api/v1/') && new URL(response.url).origin !== new URL(backend).origin) {
      output.diagnostics.network.push({ type: 'unexpected-api-origin', url: safeUrl(response.url) })
    }
    if (response.status >= 400 && !(response.status === 401 && response.url.endsWith('/auth/me')))
      output.diagnostics.network.push({ type: 'http', status: response.status, url: safeUrl(response.url) })
  })
}

async function preflight(output, url, name) {
  try {
    const response = await fetch(url, { headers: { Accept: 'application/json,text/html' } })
    record(output, name, response.ok, true, `HTTP ${response.status}`)
    if (!response.ok) return Promise.reject(new Error(`${name} returned HTTP ${response.status}`))
  } catch (error) {
    if (!output.checks.some((item) => item.name === name)) {
      record(output, name, false, true, error instanceof Error ? error.message : String(error))
    }
    throw error
  }
}

class Cdp {
  constructor(url) {
    this.url = url
    this.id = 0
    this.pending = new Map()
    this.listeners = new Map()
  }
  async connect() {
    this.socket = new WebSocket(this.url)
    await Promise.race([
      new Promise((resolvePromise, reject) => {
        this.socket.onopen = resolvePromise
        this.socket.onerror = () => reject(new Error('CDP connection failed'))
      }),
      sleep(timeout).then(() => {
        throw new Error('CDP connection timed out')
      }),
    ])
    this.socket.onmessage = ({ data }) => {
      const message = JSON.parse(data)
      if (message.id) {
        const call = this.pending.get(message.id)
        if (!call) return
        this.pending.delete(message.id)
        message.error ? call.reject(new Error(message.error.message)) : call.resolve(message.result)
      } else for (const listener of this.listeners.get(message.method) || []) listener(message.params || {})
    }
  }
  send(method, params = {}) {
    return new Promise((resolvePromise, reject) => {
      const id = ++this.id
      const timer = setTimeout(() => {
        this.pending.delete(id)
        reject(new Error(`${method} timed out`))
      }, timeout)
      this.pending.set(id, {
        resolve: (value) => {
          clearTimeout(timer)
          resolvePromise(value)
        },
        reject: (error) => {
          clearTimeout(timer)
          reject(error)
        },
      })
      this.socket.send(JSON.stringify({ id, method, params }))
    })
  }
  on(method, listener) {
    this.listeners.set(method, [...(this.listeners.get(method) || []), listener])
  }
  close() {
    this.socket?.close()
  }
}

async function navigate(cdp, url) {
  const result = await cdp.send('Page.navigate', { url })
  if (result.errorText) throw new Error(result.errorText)
  await waitFor(cdp, `document.readyState === 'complete'`)
}
async function evaluate(cdp, expression) {
  const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })
  if (result.exceptionDetails)
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text)
  return result.result.value
}
async function waitFor(cdp, expression) {
  const start = Date.now()
  while (Date.now() - start < timeout) {
    if (await evaluate(cdp, `Boolean(${expression})`).catch(() => false)) return
    await sleep(100)
  }
  throw new Error(`Timed out: ${expression}`)
}
async function pollJson(url) {
  const start = Date.now()
  while (Date.now() - start < timeout) {
    try {
      const response = await fetch(url)
      if (response.ok) return response.json()
    } catch {}
    await sleep(100)
  }
  throw new Error(`Browser debugging endpoint did not start: ${url}`)
}
async function freePort() {
  return new Promise((resolvePromise, reject) => {
    const server = createServer()
    server.unref()
    server.on('error', reject)
    server.listen(0, '127.0.0.1', () => {
      const port = server.address().port
      server.close(() => resolvePromise(port))
    })
  })
}
function ensureOk(response) {
  if (!response.ok) throw new Error(`HTTP ${response.status}`)
  return response
}
function record(output, name, ok, critical, detail = '') {
  output.checks.push({
    name,
    ok: Boolean(ok),
    critical,
    ...(detail ? { detail: redact(String(detail)).slice(0, 500) } : {}),
  })
}
function parseArgs(argv) {
  const result = {}
  for (let i = 0; i < argv.length; i += 1) {
    if (!argv[i].startsWith('--')) continue
    const [key, inline] = argv[i].slice(2).split('=', 2)
    result[key] = inline ?? argv[++i]
  }
  return result
}
function clean(value) {
  return value.toLowerCase().replace(/[^a-z0-9_.-]+/g, '-')
}
function safeUrl(value = '') {
  try {
    const url = new URL(value)
    return `${url.origin}${url.pathname.replace(/\d+/g, ':id')}`
  } catch {
    return ''
  }
}
function redact(value = '') {
  return value
    .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[email]')
    .replace(/(password|token|authorization|cookie)(["' :=]+)[^\s,"']+/gi, '$1$2[redacted]')
}
function sleep(ms) {
  return new Promise((resolvePromise) => setTimeout(resolvePromise, ms))
}
function usage(message) {
  console.error(message)
  process.exit(2)
}
