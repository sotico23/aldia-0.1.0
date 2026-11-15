import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { setTimeout as pause } from 'node:timers/promises';

export function businessDetails() {
    const text = selector => document.querySelector(selector)?.textContent?.trim() ?? '';
    const address = document.querySelector('[data-item-id="address"]');
    const phone = document.querySelector('[data-item-id^="phone:tel:"]');
    const website = document.querySelector('a[data-item-id="authority"]')?.href ?? null;
    return {
        name: text('h1'),
        address: address?.querySelector('.Io6YTe')?.textContent?.trim() ?? address?.getAttribute('aria-label')?.replace(/^(Dirección|Address):\s*/i, '') ?? '',
        phone: phone?.getAttribute('data-item-id')?.replace('phone:tel:', '') ?? '',
        category: text('button[jsaction*="category"]') || text('button.DkEaL'),
        website,
    };
}

export function mapIdentity(url) {
    try {
        const parsed = new URL(url);
        if (parsed.protocol !== 'https:' || !['www.google.com', 'google.com', 'maps.google.com'].includes(parsed.hostname)) return null;
        if (!parsed.pathname.startsWith('/maps/')) return null;
        const match = parsed.href.match(/!1s([^!/?]+)/);
        return match ? decodeURIComponent(match[1]) : parsed.searchParams.get('cid');
    } catch { return null; }
}

export class CdpClient {
    constructor(socket) {
        this.socket = socket;
        this.sequence = 0;
        this.pending = new Map();
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            const pending = this.pending.get(message.id);
            if (!pending) return;
            clearTimeout(pending.timeout);
            this.pending.delete(message.id);
            if (message.error) pending.reject(new Error('browser_command_failed'));
            else pending.resolve(message.result);
        });
        socket.addEventListener('close', () => {
            for (const pending of this.pending.values()) {
                clearTimeout(pending.timeout);
                pending.reject(new Error('browser_closed'));
            }
            this.pending.clear();
        });
    }

    static async connect(url) {
        const socket = new WebSocket(url);
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => { socket.close(); reject(new Error('browser_connection_timeout')); }, 8000);
            socket.addEventListener('open', () => { clearTimeout(timer); resolve(); }, { once: true });
            socket.addEventListener('error', () => { clearTimeout(timer); reject(new Error('browser_connection_failed')); }, { once: true });
        });
        return new CdpClient(socket);
    }

    send(method, params = {}) {
        const id = ++this.sequence;
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => { this.pending.delete(id); reject(new Error('browser_command_timeout')); }, 10000);
            this.pending.set(id, { resolve, reject, timeout });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    async evaluate(expression) {
        const result = await this.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error('page_read_failed');
        return result.result?.value;
    }

    close() { this.socket.close(); }
}

export async function withBrowser(binary, callback) {
    const profile = await mkdtemp(path.join(tmpdir(), 'redcliente-maps-'));
    let chrome;
    let browser;
    let page;
    try {
        chrome = spawn(binary, ['--headless=new', '--disable-gpu', '--disable-dev-shm-usage', '--no-first-run',
            '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0',
            `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore', shell: false });
        let spawnError = false;
        chrome.on('error', () => { spawnError = true; });
        let port;
        for (let attempt = 0; attempt < 80; attempt++) {
            if (spawnError || chrome.exitCode !== null) throw new Error('browser_start_failed');
            try { port = Number((await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]); } catch { /* The browser writes this file when ready. */ }
            if (port) break;
            await pause(200);
        }
        if (!port) throw new Error('browser_start_timeout');
        const origin = `http://127.0.0.1:${port}`;
        const version = await (await fetch(`${origin}/json/version`, { signal: AbortSignal.timeout(5000) })).json();
        browser = await CdpClient.connect(version.webSocketDebuggerUrl);
        const targets = await (await fetch(`${origin}/json/list`, { signal: AbortSignal.timeout(5000) })).json();
        const target = targets.find(target => target.type === 'page');
        if (!target) throw new Error('browser_page_missing');
        page = await CdpClient.connect(target.webSocketDebuggerUrl);
        await page.send('Page.enable');
        return await callback(page);
    } finally {
        page?.close();
        if (browser) {
            try { await browser.send('Browser.close'); } catch { /* Closing Chrome also closes its connection. */ }
            browser.close();
        }
        if (chrome && chrome.exitCode === null) {
            await Promise.race([new Promise(resolve => chrome.once('exit', resolve)), pause(2000)]);
            if (chrome.exitCode === null) chrome.kill();
        }
        if (path.dirname(path.resolve(profile)) === path.resolve(tmpdir()) && path.basename(profile).startsWith('redcliente-maps-')) {
            await rm(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 300 }).catch(() => {});
        }
    }
}

export async function pageState(page) {
    return page.evaluate(`(() => {
        const body = document.body?.innerText?.toLowerCase() ?? '';
        return {
            blocked: location.pathname.startsWith('/sorry') || !!document.querySelector('iframe[src*="recaptcha"], #captcha-form') || /unusual traffic|tráfico inusual|trafico inusual|nuestros sistemas han detectado/.test(body),
            feed: !!document.querySelector('[role="feed"]'),
            single: !!document.querySelector('h1') && location.pathname.includes('/maps/place/'),
            empty: /no results found|no se encontraron resultados|google maps no encuentra/.test(body)
        };
    })()`);
}

async function waitSearch(page) {
    for (let attempt = 0; attempt < 30; attempt++) {
        const state = await pageState(page);
        if (state.blocked) throw new Error('blocked');
        if (state.feed || state.single || state.empty) return state;
        if (attempt === 4) {
            await page.evaluate(`(() => {
                const button = [...document.querySelectorAll('button')].find(button => /^(Rechazar todo|Reject all)$/.test(button.textContent.trim()));
                button?.click();
            })()`);
        }
        await pause(500);
    }
    throw new Error('search_layout_unavailable');
}

export async function scrape(input, chromeBinary) {
    const limit = Math.max(1, Math.min(20, Number(input.limit) || 10));
    const results = [];
    let deadline;
    try {
        return await withBrowser(chromeBinary, async page => {
            const work = async () => {
                const query = `${String(input.query).slice(0, 150)} ${String(input.location).slice(0, 200)}`;
                await page.send('Page.navigate', { url: `https://www.google.com/maps/search/${encodeURIComponent(query)}/?hl=es` });
                const initial = await waitSearch(page);
                const links = new Map();
                if (initial.single) {
                    const url = await page.evaluate('location.href');
                    links.set(url, { url });
                } else if (!initial.empty) {
                    let stalled = 0;
                    for (let pass = 0; pass < 12 && links.size < limit && stalled < 3; pass++) {
                        if ((await pageState(page)).blocked) throw new Error('blocked');
                        const previous = links.size;
                        const found = await page.evaluate(`Array.from(document.querySelectorAll('[role="feed"] a[href*="/maps/place/"]')).map(a => ({url: a.href, name: a.getAttribute('aria-label') || a.textContent.trim()}))`);
                        for (const link of found ?? []) {
                            try {
                                const url = new URL(link.url);
                                if (url.hostname === 'www.google.com' && url.pathname.startsWith('/maps/place/')) {
                                    url.search = ''; url.hash = '';
                                    links.set(url.href, { url: url.href, name: link.name });
                                }
                            } catch { /* Ignore non-place links. */ }
                        }
                        stalled = links.size === previous ? stalled + 1 : 0;
                        await page.evaluate(`(() => { const feed = document.querySelector('[role="feed"]'); if (feed) feed.scrollTop = feed.scrollHeight; })()`);
                        await pause(1000);
                    }
                }
                if (!initial.empty && links.size === 0) throw new Error('search_layout_unavailable');
                for (const link of [...links.values()].slice(0, limit)) {
                    await page.send('Page.navigate', { url: link.url });
                    await pause(900);
                    for (let attempt = 0; attempt < 12; attempt++) {
                        if ((await pageState(page)).blocked) throw new Error('blocked');
                        if (await page.evaluate(`document.readyState === 'complete' && !!document.querySelector('h1') && !!document.querySelector('[data-item-id="address"]')`)) break;
                        await pause(350);
                    }
                    const details = await page.evaluate(`(${businessDetails.toString()})()`);
                    if (details?.name || link.name) results.push({ ...details, name: details?.name || link.name,
                        maps_url: link.url, place_id: mapIdentity(link.url) });
                    await pause(500);
                }
                return { status: 'completed', results };
            };
            return Promise.race([work(), new Promise((_, reject) => { deadline = setTimeout(() => reject(new Error('deadline')), 190000); })]);
        });
    } catch (error) {
        return { status: error.message === 'blocked' ? 'blocked' : 'failed', results: [] };
    } finally { clearTimeout(deadline); }
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
    let input = '';
    for await (const chunk of process.stdin) input += chunk;
    try {
        const result = await scrape(JSON.parse(input), process.argv[2]);
        process.stdout.write(JSON.stringify(result));
    } catch { process.stdout.write(JSON.stringify({ status: 'failed', results: [] })); }
}
