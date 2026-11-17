import { build } from 'esbuild';
import ts from 'typescript';
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const root = process.cwd();
const names = ['Backend/UsuarioRol/Index','Backend/Almacenes/Index','Backend/Productos/Index','Backend/Clientes/Index','Backend/Ventas/Index','Backend/Compras/Index','Backend/Proyectos/Index','Backend/Asistencia/Index','Backend/Empleados/Index','Backend/Planificacion/Index','Backend/Calidad/Index','Backend/Boms/Index','Backend/OrdenProduccion/Index','Backend/ListaPendientes/Index','Backend/Reportes/Index','Backend/Sii/Documentos','LMS/Student/Courses/Index','Notifications/Index'];
const config = ts.readConfigFile('tsconfig.json', ts.sys.readFile).config;
const parsed = ts.parseJsonConfigFileContent(config, ts.sys, root);
const program = ts.createProgram(parsed.fileNames, parsed.options);
const checker = program.getTypeChecker();
function sample(type, name, depth = 0) {
    if (depth > 6 || !type) return null;
    if (type.isUnion()) {
        if (type.types.some(t => t.flags & ts.TypeFlags.Null)) return null;
        return sample(type.types.find(t => !(t.flags & ts.TypeFlags.Undefined)),name,depth+1);
    }
    if (type.flags & ts.TypeFlags.StringLiteral) return type.value;
    if (type.flags & ts.TypeFlags.StringLike) {
        if (/email/.test(name)) return 'correo.muy.largo.para.comprobar.el.ancho@ejemplo.test';
        if (/fecha|date|_at$/.test(name)) return '2026-11-16T12:00:00Z';
        if (/hora/.test(name)) return '09:00';
        if (/url|image|photo|foto/.test(name)) return '';
        if (/estado|status/.test(name)) return 'activo';
        return 'Nombre de prueba para revisar adaptación';
    }
    if (type.flags & ts.TypeFlags.NumberLike) return 1;
    if (type.flags & ts.TypeFlags.BooleanLike) return true;
    if (checker.isArrayType(type)) {
        if (['links','permissions','permisos','errors'].includes(name)) return [];
        return [sample(checker.getTypeArguments(type)[0],name,depth+1)];
    }
    if (type.flags & ts.TypeFlags.Any || type.flags & ts.TypeFlags.Unknown) return {};
    const result = {};
    for (const property of type.getProperties()) {
        if (property.flags & ts.SymbolFlags.Optional || name === 'filters' || name === 'filtros') continue;
        result[property.name] = sample(checker.getTypeOfSymbolAtLocation(property,property.valueDeclaration ?? property.declarations[0]),property.name,depth+1);
    }
    return result;
}
const fixtures = {};
for (const name of names) {
    const file = path.join(root,'resources/js/pages',name+'.tsx');
    const source = program.getSourceFile(file);
    if (!source) throw new Error('Missing fixture page '+name);
    const symbol = checker.getExportsOfModule(checker.getSymbolAtLocation(source)).find(s => s.name==='default');
    const signature = checker.getTypeOfSymbolAtLocation(symbol,source).getCallSignatures()[0];
    const parameter = signature.parameters[0];
    fixtures[name] = sample(checker.getTypeOfSymbolAtLocation(parameter,parameter.valueDeclaration),name);
}
fixtures['Backend/UsuarioRol/Index'] = {
    usuarios:[{id:2,name:'Albert Villlegas con nombre largo',email:'correo.extenso.para.comprobar.el.ancho@ejemplo.test',is_active:true}],
    roles:[{id:1,name:'Cajero',guard_name:'web',level:3,owner_id:null,created_by:1,permissions:[]}],
    permisos:[],grouped_permissions:{},grouped_permissions_by_resource:{},
    usuariosRoles:[{id:'1',user_id:2,user_name:'Albert Villlegas',user_avatar:null,role_id:1,role_name:'Cajero',permissions:[]}],
    publicProfiles:[{id:1,title:'Mi Tienda con título largo para probar',slug:'mi-tienda-con-un-slug-largo-para-prueba',is_official:false,is_verified:false,user:{id:2,name:'Dueño con nombre largo'}}],
    is_master:true,can_delete_users:true,user_level:0,current_user_id:1,
    masterData:{all_users:[],new_users_7days:[],new_users_7days_count:0,new_users_30days:[],new_users_30days_count:0},
};
const shared = {auth:{user:{id:1,name:'Administrador',email:'admin@ejemplo.test',roles:['Master'],permissions:['*'],is_active:true}},sidebarOpen:false,flash:{},webSettings:{},pending_orders:0};
const imports = names.map((name,i)=>`import Page${i} from './resources/js/pages/${name}';`).join('\n');
const entry = `import React,{useState} from 'react'; import {createRoot} from 'react-dom/client';
import {AppContent} from './resources/js/components/app-content';
import {SidebarProvider} from './resources/js/components/ui/sidebar';
${imports}
const pages={${names.map((n,i)=>`${JSON.stringify(n)}:Page${i}`).join(',')}};
const fixtures=${JSON.stringify(fixtures)};
window.route=(name,args)=>'/test/'+name;
window.route.current=()=>false;
class Boundary extends React.Component {state={failed:false}; static getDerivedStateFromError(){return {failed:true}} componentDidCatch(error){window.fixtureError=error.message} render(){return this.state.failed?<div>Fixture error</div>:this.props.children}}
const root=createRoot(document.getElementById('root'));
window.renderCase=(name)=>{window.fixtureError=null;const Component=pages[name]; root.render(<Boundary key={name+'-'+innerWidth}><Component {...fixtures[name]}/></Boundary>)};`;
const compiled = await build({stdin:{contents:entry,loader:'tsx',resolveDir:root},bundle:true,write:false,format:'iife',jsx:'automatic',define:{'process.env.NODE_ENV':'"production"','import.meta.env':'{}'},alias:{'@':path.join(root,'resources/js')},loader:{'.css':'empty','.svg':'dataurl','.png':'dataurl'},plugins:[{name:'fixtures',setup(builder){
    builder.onResolve({filter:/^@\/layouts\/app-layout$|^@inertiajs\/react$|^axios$/},({path:name})=>({path:name,namespace:'fixture'}));
    builder.onLoad({filter:/.*/,namespace:'fixture'},({path:name})=>({loader:'tsx',resolveDir:root,contents:
        name==='axios'?`const response={data:{data:[],success:true}}; export default {get:async()=>response,post:async()=>response,isAxiosError:()=>false};`
        :name==='@inertiajs/react'?`import {useState} from 'react';export const Head=()=>null;export const usePage=()=>({props:${JSON.stringify(shared)},url:'/test'});export const Link=({href,children,...props})=><a href={typeof href==='string'?href:href?.url} {...props}>{children}</a>;export const router={reload(){},get(){},post(){},put(){},delete(){},on(){return ()=>{}}};export function useForm(initial={}){const [data,set]=useState(initial);return {data,setData:(key,value)=>set(old=>typeof key==='string'?{...old,[key]:value}:key),errors:{},processing:false,recentlySuccessful:false,reset(){},clearErrors(){},get(){},post(){},put(){},patch(){},delete(){},transform(){return this}}}`
        :`import {AppContent} from '@/components/app-content';import {SidebarProvider} from '@/components/ui/sidebar';export default function Layout({children}){return <SidebarProvider defaultOpen={false}>{innerWidth>=1024&&<aside style={{width:256,flexShrink:0}}>Menú</aside>}<AppContent variant="sidebar"><div data-platform-page style={{width:'100%',minWidth:0}}>{children}</div></AppContent></SidebarProvider>}`
    }));
}}]});
const testing=path.join(root,'storage/framework/testing');mkdirSync(testing,{recursive:true});
const temporary=mkdtempSync(path.join(testing,'platform-responsive-'));
const manifest=JSON.parse(readFileSync('public/build/manifest.json','utf8'));
const css=readFileSync(path.join('public/build',manifest['resources/css/app.css'].file),'utf8');
const file=path.join(temporary,'index.html');writeFileSync(file,`<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css} *,*::before,*::after{animation:none!important;transition:none!important}</style></head><body><div id="root"></div><script>${compiled.outputFiles[0].text.replaceAll('</script','<\\/script')}</script></body></html>`);
const chrome=spawn(process.env.CHROME_BINARY,['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${path.join(temporary,'profile')}`,'about:blank'],{stdio:'ignore',windowsHide:true});
const delay=ms=>new Promise(r=>setTimeout(r,ms));let socket;
try{
    const portFile=path.join(temporary,'profile/DevToolsActivePort');for(let i=0;!existsSync(portFile);i++){if(i>200)throw new Error('Chrome startup timeout');await delay(50)}
    const port=readFileSync(portFile,'utf8').split('\n')[0].trim();
    const targets=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject});let id=0;const waiting=new Map();socket.onmessage=({data})=>{const msg=JSON.parse(data);if(waiting.has(msg.id)){const {resolve,reject}=waiting.get(msg.id);waiting.delete(msg.id);msg.error?reject(new Error(JSON.stringify(msg.error))):resolve(msg.result)}};
    const send=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;waiting.set(key,{resolve,reject});socket.send(JSON.stringify({id:key,method,params}))});
    await send('Page.navigate',{url:pathToFileURL(file).href});
    for(let i=0;i<100;i++){if((await send('Runtime.evaluate',{expression:'typeof window.renderCase',returnByValue:true})).result.value==='function')break;await delay(50)}
    const results=[];
    for(const [width,height] of [[320,568],[390,844],[667,375],[768,1024],[1440,900]]){
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        for(const name of names){
            const probe=await send('Runtime.evaluate',{awaitPromise:true,returnByValue:true,expression:`(async()=>{
                window.renderCase(${JSON.stringify(name)});await new Promise(r=>setTimeout(r,120));
                if(window.fixtureError)return {page:${JSON.stringify(name)},width:innerWidth,error:window.fixtureError};
                const failures=[];const region=document.querySelector('[data-platform-page]');
                if(!region)return {page:${JSON.stringify(name)},width:innerWidth,error:'Page not rendered'};
                const right=region.getBoundingClientRect().right;
                const outside=[...region.querySelectorAll('[data-slot="card"],button,input,[data-slot="tabs-list"],section')].filter(el=>{
                    if(el.offsetParent===null||el.closest('[role="dialog"]'))return false;
                    let parent=el.parentElement;while(parent&&parent!==region){if(['auto','scroll'].includes(getComputedStyle(parent).overflowX))return false;parent=parent.parentElement}
                    const box=el.getBoundingClientRect();return box.right>right+1||box.left<region.getBoundingClientRect().left-1;
                }).slice(0,6).map(el=>el.tagName+':'+el.textContent.slice(0,65));
                if(outside.length)failures.push(...outside);
                if(region.scrollWidth>region.clientWidth+1)failures.push('Page content exceeds available width');
                return {page:${JSON.stringify(name)},width:innerWidth,failures};
            })()`});
            if(probe.exceptionDetails)throw new Error(JSON.stringify(probe.exceptionDetails));results.push(probe.result.value);
        }
    }
    process.stdout.write(JSON.stringify(results));
}finally{
    socket?.close();chrome.kill();await new Promise(resolve=>{if(chrome.exitCode!==null)resolve();else{chrome.once('exit',resolve);setTimeout(resolve,3000)}});
    if(!path.resolve(temporary).startsWith(path.resolve(testing)+path.sep))throw new Error('Invalid fixture path');
    rmSync(temporary,{recursive:true,force:true,maxRetries:10,retryDelay:200});
}
