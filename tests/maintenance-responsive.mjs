import { build } from 'esbuild';
import { spawn } from 'node:child_process';
import {
    existsSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const root = process.cwd();
const payload = JSON.parse(readFileSync(0, 'utf8'));
const testing = path.join(root, 'storage/framework/testing');
mkdirSync(testing, { recursive: true });
const temporary = mkdtempSync(path.join(testing, 'maintenance-responsive-'));
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync(
    path.join('public/build', manifest['resources/css/app.css'].file),
    'utf8',
);
const compiled = await build({
    stdin: {
        contents: `import React from 'react'; import {createRoot} from 'react-dom/client';
        import Maintenance from './resources/js/pages/Backend/ConfiguracionWeb/Maintenance';
        const props = ${JSON.stringify(payload)};
        createRoot(document.getElementById('root')).render(<Maintenance {...props} />);`,
        loader: 'tsx',
        resolveDir: root,
    },
    bundle: true,
    write: false,
    format: 'iife',
    jsx: 'automatic',
    define: { 'process.env.NODE_ENV': '"production"' },
    alias: { '@': path.join(root, 'resources/js') },
    plugins: [
        {
            name: 'maintenance-fixture',
            setup(builder) {
                builder.onResolve(
                    {
                        filter: /^@\/layouts\/app-layout$|^@inertiajs\/react$|^axios$/,
                    },
                    ({ path: name }) => ({ path: name, namespace: 'fixture' }),
                );
                builder.onLoad(
                    { filter: /.*/, namespace: 'fixture' },
                    ({ path: name }) => ({
                        loader: 'tsx',
                        resolveDir: root,
                        contents:
                            name === 'axios'
                                ? `export default {post: async () => ({data: {success: true, command:'route:clear', output:'${'long-output-'.repeat(500)}', exit_code:0, executed_at:'2026-11-16T20:00:00Z'}}), isAxiosError:()=>false};`
                                : name === '@inertiajs/react'
                                  ? `export const Head=()=>null; export const Link=({href,children,...props})=><a href={typeof href==='string'?href:href.url} {...props}>{children}</a>; export const router={reload(){}};`
                                  : `export default function Layout({children}) {return <div style={{display:'flex',width:'100%',minWidth:0}}>{innerWidth>=1024&&<aside style={{width:256,flexShrink:0}}>Menú</aside>}<main style={{minWidth:0,flex:1}}>{children}</main></div>}`,
                    }),
                );
            },
        },
    ],
});
const html = path.join(temporary, 'index.html');
writeFileSync(
    html,
    `<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}\n*,*::before,*::after{animation:none!important;transition:none!important}</style></head><body><div id="root"></div><script>${compiled.outputFiles[0].text.replaceAll('</script', '<\\/script')}</script></body></html>`,
);
const chrome = spawn(
    process.env.CHROME_BINARY,
    [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        '--remote-debugging-port=0',
        `--user-data-dir=${path.join(temporary, 'profile')}`,
        'about:blank',
    ],
    { stdio: 'ignore', windowsHide: true },
);
let socket;
try {
    const portFile = path.join(temporary, 'profile/DevToolsActivePort');
    for (let i = 0; !existsSync(portFile); i++) {
        if (i > 200) throw new Error('Chrome did not start');
        await delay(50);
    }
    const port = readFileSync(portFile, 'utf8').split('\n')[0].trim();
    const targets = await (
        await fetch(`http://127.0.0.1:${port}/json/list`)
    ).json();
    socket = new WebSocket(
        targets.find((target) => target.type === 'page').webSocketDebuggerUrl,
    );
    await new Promise((resolve, reject) => {
        socket.onopen = resolve;
        socket.onerror = reject;
    });
    let id = 0;
    const requests = new Map();
    socket.onmessage = ({ data }) => {
        const result = JSON.parse(data);
        if (requests.has(result.id)) {
            const { resolve, reject } = requests.get(result.id);
            requests.delete(result.id);
            result.error
                ? reject(new Error(JSON.stringify(result.error)))
                : resolve(result.result);
        }
    };
    const send = (method, params = {}) =>
        new Promise((resolve, reject) => {
            const key = ++id;
            requests.set(key, { resolve, reject });
            socket.send(JSON.stringify({ id: key, method, params }));
        });
    const results = [];
    for (const [width, height] of [
        [320, 568],
        [390, 844],
        [667, 375],
        [768, 1024],
        [1440, 900],
    ]) {
        await send('Emulation.setDeviceMetricsOverride', {
            width,
            height,
            deviceScaleFactor: 1,
            mobile: false,
        });
        const previousOrigin = (
            await send('Runtime.evaluate', {
                expression: 'performance.timeOrigin',
                returnByValue: true,
            })
        ).result.value;
        await send('Page.navigate', { url: pathToFileURL(html).href });
        for (let i = 0; i < 100; i++) {
            const ready = await send('Runtime.evaluate', {
                expression: `performance.timeOrigin !== ${previousOrigin} && !!document.querySelector('section button')`,
                returnByValue: true,
            });
            if (ready.result.value) break;
            await delay(50);
        }
        const probe = await send('Runtime.evaluate', {
            awaitPromise: true,
            returnByValue: true,
            expression: `(async()=>{
            const failures=[]; const wait=()=>new Promise(r=>setTimeout(r,80));
            const check=(ok,message)=>{if(!ok)failures.push(message)};
            check(document.documentElement.scrollWidth<=innerWidth+1,'Page overflows horizontally');
            let dialogs=0;
            const commands=${JSON.stringify(payload.commands)};
            for(const command of commands.filter(c=>c.confirm)){
                const card=[...document.querySelectorAll('section [data-slot="card"]')].find(c=>c.querySelector('code').textContent.trim()===command.key);
                card.querySelector('button').click(); await wait();
                const dialog=document.querySelector('[role="dialog"][data-state="open"]');
                check(!!dialog,'Missing dialog: '+command.key); if(!dialog)continue;
                dialogs++; const box=dialog.getBoundingClientRect(); const style=getComputedStyle(dialog);
                check(box.width<=513,'Dialog too wide: '+command.key);
                check(box.left>=15&&box.right<=innerWidth-15,'Dialog horizontal bounds: '+command.key);
                check(box.top>=15&&box.bottom<=innerHeight-15,'Dialog vertical bounds: '+command.key);
                check(parseFloat(style.paddingLeft)>=20&&parseFloat(style.paddingTop)>=20,'Dialog missing padding');
                check(dialog.scrollWidth<=dialog.clientWidth+1,'Dialog content overflows');
                dialog.scrollTop=dialog.scrollHeight; await wait();
                for(const button of dialog.querySelectorAll('[data-slot="dialog-footer"] button')){
                    const rect=button.getBoundingClientRect();check(rect.left>=box.left&&rect.right<=box.right&&rect.top>=box.top&&rect.bottom<=box.bottom,'Button is clipped');
                    check(rect.height>=39,'Button is too short');
                }
                [...dialog.querySelectorAll('button')].find(b=>b.textContent==='Cancelar').click(); await wait();
            }
            const safe=[...document.querySelectorAll('section [data-slot="card"]')].find(c=>c.querySelector('code').textContent.trim()==='route:clear');
            safe.querySelector('button').click(); await wait();
            check(document.documentElement.scrollWidth<=innerWidth+1,'Long output overflows page');
            check(!!document.querySelector('pre'),'Missing command result');
            return {width:innerWidth,height:innerHeight,dialogs,failures};
        })()`,
        });
        if (probe.exceptionDetails)
            throw new Error(JSON.stringify(probe.exceptionDetails));
        results.push(probe.result.value);
    }
    process.stdout.write(JSON.stringify(results));
} finally {
    socket?.close();
    chrome.kill();
    await new Promise((resolve) => {
        if (chrome.exitCode !== null) resolve();
        else {
            chrome.once('exit', resolve);
            setTimeout(resolve, 3000);
        }
    });
    if (!path.resolve(temporary).startsWith(path.resolve(testing) + path.sep))
        throw new Error('Unexpected fixture directory');
    rmSync(temporary, {
        recursive: true,
        force: true,
        maxRetries: 10,
        retryDelay: 200,
    });
}
