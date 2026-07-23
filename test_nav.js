import fs from 'fs';

const userPermissions = JSON.parse(fs.readFileSync('test_perms.json', 'utf8'));

export function canAny(permission, userPermissions) {
    if (!permission) return true;
    if (!userPermissions) return false;

    const perms = Array.isArray(permission) ? permission : [permission];
    return perms.some((p) => {
        if (p.endsWith(".*")) {
            const prefix = p.slice(0, -1);
            return userPermissions.some((up) => up.startsWith(prefix));
        }
        return userPermissions.includes(p);
    });
}

function filterNavItemsRecursive(items, userPermissions, hasWildcard, userRoles) {
    return items
        .filter((item) => {
            if (hasWildcard) return true;
            if (!item.permission) return true;
            return canAny(item.permission, userPermissions);
        })
        .map((item) => {
            if (!item.items) return item;
            const filteredSubItems = filterNavItemsRecursive(item.items, userPermissions, hasWildcard, userRoles);
            return { ...item, items: filteredSubItems };
        })
        .filter((item) => {
            if (item.items && item.items.length === 0) {
                return false;
            }
            return true;
        });
}

// Minimal items to test
const mainNavItems = [
    {
        title: "Gestión Comercial",
        permission: "comercial.*",
        items: [
            {
                title: "Catálogo",
                items: [
                    { title: "Categorías", permission: "comercial.categorias.viewAny" },
                    { title: "Productos", permission: "comercial.productos.viewAny" }
                ]
            }
        ]
    },
    {
        title: "Administración",
        permission: [
            "admin.usuarios.viewAny",
            "admin.roles.viewAny"
        ],
        items: [
            { title: "Usuarios y Roles", permission: ["admin.usuarios.viewAny", "admin.roles.viewAny"] }
        ]
    }
];

const filtered = filterNavItemsRecursive(mainNavItems, userPermissions, false, []);
console.log(JSON.stringify(filtered, null, 2));
