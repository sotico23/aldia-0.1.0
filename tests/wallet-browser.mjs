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
const testing = path.join(root, 'storage/framework/testing');
mkdirSync(testing, { recursive: true });
const temporary = mkdtempSync(path.join(testing, 'wallet-browser-'));
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = manifest['resources/js/app.tsx'].css
    .map((file) => readFileSync(path.join('public/build', file), 'utf8'))
    .join('\n');
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const linkFixture = JSON.parse(process.env.WALLET_LINK_FIXTURE || 'null');
const compiled = await build({
    stdin: {
        contents: `import React from 'react'; import {createRoot} from 'react-dom/client';
            import Wallet from './resources/js/pages/Backend/Pos/CajeroWallet';
            import PaymentLink from './resources/js/pages/Backend/Wallets/PaymentLink';
            import {Html5Qrcode} from 'html5-qrcode';
            const root = createRoot(document.getElementById('root'));
            const linkFixture = ${JSON.stringify(linkFixture)};
            window.renderPaymentLink = (mode) => {
                window.submissions=[];
                const payment={...linkFixture.payment,is_owner:mode==='owner',can_pay:mode==='payer',status:mode==='paid'?'paid':mode==='cancelled'?'cancelled':'pending'};
                root.render(<PaymentLink key={mode+'-'+innerWidth} {...linkFixture} payment={payment}/>);
            };
            window.decodePaymentQr = async () => {
                const img=document.querySelector('img[alt="Código QR del enlace de pago"]');
                await img.decode();const canvas=document.createElement('canvas');canvas.width=canvas.height=640;
                canvas.getContext('2d').drawImage(img,0,0,640,640);
                const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
                const reader=document.createElement('div');reader.id='qr-test-reader';document.body.append(reader);
                const scanner=new Html5Qrcode(reader.id, {verbose:false, useBarCodeDetectorIfSupported:false});
                try {return await scanner.scanFile(new File([blob],'payment.png',{type:'image/png'}),false);}
                finally {scanner.clear();reader.remove();}
            };
            window.renderWallet = (balance = 100) => {
                window.submissions = [];
                root.render(<Wallet key={balance+'-'+innerWidth} wallet={{id:1,user_id:1,account_number:'1234567890123456',balance,currency:'CLP',status:'active',total_comisiones:0}}
                    comisiones_mes={0} transacciones={{data:[],links:[],meta:{from:0,to:0,total:0}}} moneda="CLP"
                    payment_requests={{links:[],data:[{id:42,amount:'25.00',currency:'CLP',note:'Pago por pedido',status:'pending',incoming:true,counterparty:'Usuario con nombre extenso de prueba',created_at:'2026-11-18T10:00:00Z'}]}} />);
            };`,
        resolveDir: root,
        loader: 'tsx',
    },
    bundle: true,
    write: false,
    format: 'iife',
    jsx: 'automatic',
    define: { 'process.env.NODE_ENV': '"production"' },
    alias: { '@': path.join(root, 'resources/js') },
    plugins: [
        {
            name: 'wallet-fixtures',
            setup(builder) {
                builder.onResolve(
                    {
                        filter: /^@inertiajs\/react$|^axios$|^@\/layouts\/app-layout$/,
                    },
                    ({ path }) => ({ path, namespace: 'fixture' }),
                );
                builder.onLoad(
                    { filter: /.*/, namespace: 'fixture' },
                    ({ path }) => ({
                        resolveDir: root,
                        loader: 'tsx',
                        contents:
                            path === 'axios'
                                ? `export default {get:async()=>({data:{name:'Usuario destinatario',account_number:'9999999999999999',currency:'CLP'}}),isAxiosError:()=>false};`
                                : path === '@inertiajs/react'
                                  ? `import {useState,useRef} from 'react';
                            export const Head=()=>null; export const usePoll=()=>{};
                            export const usePage=()=>({props:{auth:{user:{roles:[],permissions:['finanzas.wallets.view']}}}});
                            export const Link=({href,children,...props})=><a href={typeof href==='string'?href:href.url} {...props}>{children}</a>;
                            export const router={get(){}};
                            export function useForm(initial){
                                const [data,setData]=useState(initial);const [errors,setErrors]=useState({});const transform=useRef(v=>v);
                                const form={data,errors,processing:false,
                                    setData:(key,value)=>setData(old=>typeof key==='string'?{...old,[key]:value}:key),
                                    setError:(key,value)=>setErrors(old=>({...old,[key]:value})),clearErrors:()=>setErrors({}),reset:()=>setData(initial),
                                    transform:(callback)=>{transform.current=callback;},
                                    post:(url,options)=>{window.submissions.push({url,data:transform.current(data)});options?.onSuccess?.();}};return form;}`
                                  : `export default ({children})=><main style={{maxWidth:'100%',minWidth:0}}>{children}</main>`,
                    }),
                );
            },
        },
    ],
});
const html = path.join(temporary, 'index.html');
writeFileSync(
    html,
    `<html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}\n*,*::before,*::after{animation:none!important;transition:none!important}</style></head><body><div id="root"></div><script>${compiled.outputFiles[0].text.replaceAll('</script', '<\\/script')}</script></body></html>`,
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
        targets.find((t) => t.type === 'page').webSocketDebuggerUrl,
    );
    await new Promise((resolve, reject) => {
        socket.onopen = resolve;
        socket.onerror = reject;
    });
    let id = 0;
    const pending = new Map();
    socket.onmessage = ({ data }) => {
        const result = JSON.parse(data);
        const item = pending.get(result.id);
        if (item) {
            pending.delete(result.id);
            result.error
                ? item.reject(new Error(JSON.stringify(result.error)))
                : item.resolve(result.result);
        }
    };
    const send = (method, params = {}) =>
        new Promise((resolve, reject) => {
            const key = ++id;
            pending.set(key, { resolve, reject });
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
        const previous = (
            await send('Runtime.evaluate', {
                expression: 'performance.timeOrigin',
                returnByValue: true,
            })
        ).result.value;
        await send('Page.navigate', { url: pathToFileURL(html).href });
        for (let i = 0; i < 100; i++) {
            if (
                (
                    await send('Runtime.evaluate', {
                        expression: `performance.timeOrigin!==${previous}&&typeof window.renderWallet==='function'`,
                        returnByValue: true,
                    })
                ).result.value
            )
                break;
            await delay(50);
        }
        const probe = await send('Runtime.evaluate', {
            awaitPromise: true,
            returnByValue: true,
            expression: `(async()=>{
            const failures=[];const check=(ok,message)=>{if(!ok)failures.push(message)};const wait=()=>new Promise(r=>setTimeout(r,100));
            const button=text=>[...document.querySelectorAll('button')].find(el=>el.textContent.trim()===text);
            const type=(id,value)=>{const el=document.getElementById(id);Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,value);el.dispatchEvent(new Event('input',{bubbles:true}));};
            const dialogBounds=()=>{const dialog=document.querySelector('[role="dialog"][data-state="open"]');check(!!dialog,'Missing dialog');if(!dialog)return;const b=dialog.getBoundingClientRect();check(b.left>=8&&b.right<=innerWidth-8,'Dialog outside horizontal viewport');check(b.top>=8&&b.bottom<=innerHeight-8,'Dialog outside vertical viewport');check(dialog.scrollWidth<=dialog.clientWidth+1,'Dialog horizontal overflow');};
            window.renderWallet();await wait();
            check(document.documentElement.scrollWidth<=innerWidth+1,'Page horizontal overflow');
            check(document.body.innerText.includes('1234567890123456'),'Missing wallet number');
            for(const mode of ['Transferir','Solicitar pago']){
                const start=button(mode);check(!!start&&!start.disabled,'Missing enabled '+mode);start?.click();await wait();dialogBounds();
                type('wallet-account','9999999999999999');type('wallet-amount','10.01');type('wallet-note','Pedido de prueba');await wait();
                button('Revisar datos')?.click();await wait();
                check(document.body.innerText.includes('Usuario destinatario'),'Missing confirmed recipient');
                check(window.submissions.length===(mode==='Transferir'?0:1),'Operation posted before confirmation');
                const confirm=button(mode==='Transferir'?'Confirmar transferencia':'Enviar solicitud de pago');check(!!confirm,'Missing final confirmation');confirm?.click();await wait();
                check(!document.querySelector('[role="dialog"][data-state="open"]'),'Operation dialog did not close');
                const sent=window.submissions.at(-1);check(sent?.url===(mode==='Transferir'?'/billetera/transferencias':'/billetera/solicitudes'),'Wrong operation endpoint');
                check(sent?.data.amount==='10.01'&&sent?.data.account_number==='9999999999999999','Wrong transfer payload');
            }
            button('Revisar pago')?.click();await wait();dialogBounds();
            check(window.submissions.length===2,'Payment posted without confirmation');
            button('Confirmar pago')?.click();await wait();
            check(window.submissions.at(-1)?.data.decision==='accept','Payment consent not submitted');
            window.renderWallet(0);await wait();check(button('Transferir')?.disabled,'Empty wallet can transfer');
            check(!button('Solicitar pago')?.disabled,'Empty wallet cannot request payment');
            button('Revisar pago')?.click();await wait();dialogBounds();check(button('Confirmar pago')?.disabled,'Insufficient balance can confirm payment');
            return {width:innerWidth,failures};
        })()`,
        });
        if (probe.exceptionDetails)
            throw new Error(JSON.stringify(probe.exceptionDetails));
        results.push(probe.result.value);
        if (linkFixture) {
            const linkProbe = await send('Runtime.evaluate', {
                awaitPromise: true,
                returnByValue: true,
                expression: `(async()=>{
                    const failures=[];const check=(ok,message)=>{if(!ok)failures.push(message)};const wait=()=>new Promise(r=>setTimeout(r,100));
                    const button=text=>[...document.querySelectorAll('button')].find(el=>el.textContent.trim()===text);
                    window.renderPaymentLink('owner');await wait();
                    check(document.documentElement.scrollWidth<=innerWidth+1,'Owner payment page overflows');
                    const img=document.querySelector('img[alt="Código QR del enlace de pago"]');check(!!img,'Missing QR');
                    if(img){await img.decode();const b=img.getBoundingClientRect();check(b.left>=0&&b.right<=innerWidth,'QR outside viewport');}
                    check(!!button('Copiar enlace')&&!!button('Compartir'),'Missing sharing buttons');
                    check(!!document.querySelector('a[download$=".svg"]'),'Missing QR download');
                    check(!button('Revisar y pagar'),'Owner can pay own link');
                    if(innerWidth===390){try{check(await window.decodePaymentQr()===${JSON.stringify(linkFixture.payment.url)},'QR decoded to wrong URL');}catch(error){failures.push('QR decoding failed: '+error);}}
                    window.renderPaymentLink('payer');await wait();check(document.documentElement.scrollWidth<=innerWidth+1,'Payer payment page overflows');
                    button('Revisar y pagar')?.click();await wait();
                    const dialog=document.querySelector('[role="dialog"][data-state="open"]');check(!!dialog,'Payment confirmation missing');
                    if(dialog){const b=dialog.getBoundingClientRect();check(b.left>=8&&b.right<=innerWidth-8&&b.top>=8&&b.bottom<=innerHeight-8,'Payment confirmation outside viewport');}
                    check(window.submissions.length===0,'Opening QR charged before confirmation');
                    button('Confirmar pago')?.click();await wait();check(window.submissions.length===1,'Confirmation did not submit once');
                    check(window.submissions[0]?.url.endsWith('/pagar'),'Wrong QR payment endpoint');
                    window.renderPaymentLink('insufficient');await wait();check(button('Revisar y pagar')?.disabled,'Insufficient wallet can pay link');
                    for(const status of ['paid','cancelled']){window.renderPaymentLink(status);await wait();check(!button('Revisar y pagar'),'Closed link remains payable');check(!document.querySelector('img[alt="Código QR del enlace de pago"]'),'Closed link still shows collectible QR');}
                    return {width:innerWidth,kind:'payment-link',failures};
                })()`,
            });
            if (linkProbe.exceptionDetails)
                throw new Error(JSON.stringify(linkProbe.exceptionDetails));
            results.push(linkProbe.result.value);
        }
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
