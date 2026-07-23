// Vamos a simular las permissions
const permissions = [
    "comercial.prospectos.viewAny",
    "admin.usuarios.viewAny"
];

// Re-implementar la logica aca
function canAny(permission, userPermissions) {
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
console.log(canAny("comercial.*", permissions));
console.log(canAny(["admin.usuarios.viewAny", "admin.roles.viewAny"], permissions));