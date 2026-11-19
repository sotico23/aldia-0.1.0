import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const directory = path.resolve('storage/framework/testing');
mkdirSync(directory, { recursive: true });
const temporary = mkdtempSync(path.join(directory, 'dashboard-wallet-'));

try {
    const outfile = path.join(temporary, 'dashboard.cjs');
    await build({
        stdin: {
            contents: `import React from 'react';
                import { renderToStaticMarkup } from 'react-dom/server';
                import Dashboard from './resources/js/pages/Backend/Dashboard';
                export const render = () => renderToStaticMarkup(React.createElement(Dashboard, { savedLayout: [] }));`,
            resolveDir: process.cwd(),
            loader: 'tsx',
        },
        outfile,
        bundle: true,
        platform: 'node',
        format: 'cjs',
        jsx: 'automatic',
        external: ['react', 'react/*', 'react-dom/*'],
        plugins: [{
            name: 'dashboard-fixtures',
            setup(build) {
                build.onResolve({ filter: /^@inertiajs\/react$/ }, () => ({ path: 'inertia', namespace: 'fixture' }));
                build.onResolve({ filter: /^@\/(components\/dashboard\/|layouts\/app-layout)/ }, () => ({ path: 'layout', namespace: 'fixture' }));
                build.onLoad({ filter: /.*/, namespace: 'fixture' }, ({ path }) => ({
                    contents: path === 'inertia'
                        ? `import React from 'react';
                           export const Head = () => null;
                           export const usePage = () => ({ props: { auth: { user: { permissions: globalThis.walletPermissions } } } });
                           export const Link = ({href, children, ...props}) => React.createElement('a', {...props, href: typeof href === 'string' ? href : href.url}, children);`
                        : `import React from 'react'; export default ({children}) => React.createElement('section', {'data-dashboard-layout': true}, children);`,
                    loader: 'js',
                    resolveDir: process.cwd(),
                }));
            },
        }],
    });
    const { render } = createRequire(import.meta.url)(outfile);
    for (const permissions of [['finanzas.wallets.view'], ['finanzas.wallets.viewAny'], ['*']]) {
        globalThis.walletPermissions = permissions;
        const html = render();
        assert.match(html, /href="\/billetera"/);
        assert.match(html, /Mi billetera/);
        assert.match(html, /w-full sm:w-auto/);
        assert.ok(html.indexOf('Mi billetera') < html.lastIndexOf('data-dashboard-layout'));
        assert.doesNotMatch(html, /href="\/billeteras"/);
    }
    for (const permissions of [[], ['comercial.productos.viewAny']]) {
        globalThis.walletPermissions = permissions;
        assert.doesNotMatch(render(), /Mi billetera/);
    }
    console.log('Dashboard wallet: personal route, persistent placement and five permission scenarios passed.');
} finally {
    delete globalThis.walletPermissions;
    rmSync(temporary, { recursive: true, force: true });
}
