import { Head, useForm, router } from '@inertiajs/react';
import {
    Eye,
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    Shield,
    Lock,
    Users,
    CheckCircle2,
    Store,
    UserCog,
    KeyRound,
    ChevronDown,
    ChevronRight,
    X,
    Check,
    AlertTriangle,
    Crown,
    UserPlus,
    Tag,
    Globe,
    CalendarDays,
    Settings,
    ShoppingBag,
    Truck,
    Factory,
    CreditCard,
    UsersRound,
    ClipboardList,
    Navigation,
    ShoppingCart,
    Calendar,
    GraduationCap,
    Megaphone,
    Activity,
    FileText,
    Zap,
    Gift,
    Bot,
    Building2,
    Package,
    HardHat,
    Layers,
    Coins,
    Heart,
    BarChart,
    BarChart2,
    CheckSquare,
    Square,
    HelpCircle,
    Star,
    ShieldCheck,
} from 'lucide-react';
import { useState, useMemo, useRef } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { mainNavItems, adminNavItems } from '@/config/navigation';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
    rut?: string | null;
    telefono?: string | null;
    direccion?: string | null;
    profile_photo_url?: string;
    is_active?: boolean;
    banned_at?: string | null;
    owner_id?: number | null;
    created_at?: string;
}

interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

interface Role {
    id: number;
    name: string;
    guard_name: string;
    level: number;
    owner_id: number | null;
    created_by: number | null;
    permissions: Permission[];
}

interface UsuarioRol {
    id: string;
    user_id: number;
    user_name: string;
    user_avatar: string | null;
    role_id: number;
    role_name: string;
    permissions: { id: number; name: string }[];
}

interface PublicProfile {
    id: number;
    title: string;
    slug: string;
    is_official: boolean;
    is_verified: boolean;
    user: { id: number; name: string };
}

interface MasterData {
    all_users: User[];
    new_users_7days: User[];
    new_users_7days_count: number;
    new_users_30days: User[];
    new_users_30days_count: number;
}

interface Props {
    usuarios: User[];
    roles: Role[];
    permisos: Permission[];
    grouped_permissions: Record<string, Record<string, { id: number; name: string; friendly_name: string }[]>>;
    grouped_permissions_by_resource: Record<string, Record<string, Record<string, { id: number; name: string; friendly_name: string }[]>>>;
    usuariosRoles: UsuarioRol[];
    publicProfiles: PublicProfile[];
    is_master: boolean;
    user_level: number;
    masterData: MasterData | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles y Permisos', href: '/usuarios-roles' },
];

const LEVEL_COLORS: Record<number, { bg: string; text: string; border: string; label: string }> = {
    0: { bg: 'from-red-600 to-rose-700', text: 'text-red-600', border: 'border-red-500/30', label: 'Master' },
    1: { bg: 'from-orange-500 to-amber-600', text: 'text-orange-600', border: 'border-orange-400/30', label: 'Super Admin' },
    2: { bg: 'from-blue-500 to-indigo-600', text: 'text-blue-600', border: 'border-blue-400/30', label: 'Admin' },
    3: { bg: 'from-emerald-500 to-teal-600', text: 'text-emerald-600', border: 'border-emerald-400/30', label: 'Usuario' },
};

function getLevelColor(level: number) {
    return LEVEL_COLORS[level] ?? { bg: 'from-gray-500 to-gray-600', text: 'text-gray-600', border: 'border-gray-400/30', label: 'Nivel ' + level };
}

function getModuleIcon(module: string): React.ReactNode {
    const iconMap: Record<string, React.ReactNode> = {
        'SISTEMA': <Settings className="h-4 w-4" />,
        'COMERCIAL': <ShoppingBag className="h-4 w-4" />,
        'OPERACIONES': <Truck className="h-4 w-4" />,
        'MRP': <Factory className="h-4 w-4" />,
        'FINANZAS Y FACTURACIÓN': <CreditCard className="h-4 w-4" />,
        'PAGOS EN LÍNEA': <Coins className="h-4 w-4" />,
        'GESTIÓN HUMANA': <UsersRound className="h-4 w-4" />,
        'PROYECTOS': <ClipboardList className="h-4 w-4" />,
        'LOGÍSTICA': <Navigation className="h-4 w-4" />,
        'PUNTO DE VENTA (POS)': <ShoppingCart className="h-4 w-4" />,
        'CITAS Y RESERVAS': <Calendar className="h-4 w-4" />,
        'PLATAFORMA DE APRENDIZAJE': <GraduationCap className="h-4 w-4" />,
        'MARKETING': <Megaphone className="h-4 w-4" />,
        'MONITOREO': <Activity className="h-4 w-4" />,
        'GESTIÓN SII (DTE)': <FileText className="h-4 w-4" />,
        'MARKETPLACE': <Store className="h-4 w-4" />,
        'RECOMPENSAS': <Gift className="h-4 w-4" />,
        'AUTOMATIZACIONES': <Bot className="h-4 w-4" />,
        'OTROS / PERMISOS TRANSVERSALES': <Layers className="h-4 w-4" />,
    };
    return iconMap[module] ?? <Building2 className="h-4 w-4" />;
}

/**
 * Genera una etiqueta amigable y contextual para un permiso
 * basado en su nombre técnico (ej: 'comercial.productos.create' -> 'Crear Productos')
 */
function getFriendlyPermissionLabel(permission: { name: string; friendly_name: string }): string {
    const parts = permission.name.split('.');

    // Si no tiene la estructura esperada (modulo.recurso.accion), usar el nombre amigable original
    if (parts.length !== 3) {
        return permission.friendly_name;
    }

    const [, resource, action] = parts;

    // Formatear el recurso: reemplazar guiones/underscores por espacios y capitalizar
    const formattedResource = resource
        .replace(/[-_]/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());

    // Mapear acciones a texto en español
    const actionMap: Record<string, string> = {
        'create': 'Crear / Agregar',
        'store': 'Crear / Agregar',
        'edit': 'Editar / Modificar',
        'update': 'Editar / Modificar',
        'delete': 'Eliminar',
        'destroy': 'Eliminar',
        'viewAny': 'Ver Listado de',
        'index': 'Ver Listado de',
        'view': 'Ver Detalle de',
        'show': 'Ver Detalle de',
        'export': 'Exportar',
        'import': 'Importar',
    };

    const actionLabel = actionMap[action] ?? action.charAt(0).toUpperCase() + action.slice(1);

    // Acciones que no necesitan "de" antes del recurso
    const noPrepositionActions = ['create', 'store', 'export', 'import'];

    if (noPrepositionActions.includes(action)) {
        return `${actionLabel} ${formattedResource}`;
    }

    return `${actionLabel} ${formattedResource}`;
}

export default function Index({
    usuarios,
    roles,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    permisos,
    grouped_permissions,
    grouped_permissions_by_resource,
    usuariosRoles,
    publicProfiles,
    is_master,
    user_level,
    masterData,
}: Props) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('admin.usuarios.create');
    const canEdit = hasPermission('admin.usuarios.edit');
    const canDelete = hasPermission('admin.usuarios.delete');
    const canManageRoles = hasPermission('admin.roles.custom');
    const canSeeTiendas = user_level <= 1;

    const [activeTab, setActiveTab] = useState('asignaciones');
    const [searchQuery, setSearchQuery] = useState('');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
    const [viewingRole, setViewingRole] = useState<Role | null>(null);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [isRolOpen, setIsRolOpen] = useState(false);
    const [isPermisoOpen, setIsPermisoOpen] = useState(false);
    const [editingPermission, setEditingPermission] = useState<Permission | null>(null);
    const [gruposExpandidos, setGruposExpandidos] = useState<Record<string, boolean>>({});
    const [recursosExpandidos, setRecursosExpandidos] = useState<Record<string, boolean>>({});
    const [showAssignModal, setShowAssignModal] = useState(false);
    const [assignUserId, setAssignUserId] = useState('');
    const [assignRoleId, setAssignRoleId] = useState('');
    const [userSearch, setUserSearch] = useState('');
    const [roleSearch, setRoleSearch] = useState('');
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [isUserEditOpen, setIsUserEditOpen] = useState(false);
    const [resettingPasswordUser, setResettingPasswordUser] = useState<User | null>(null);
    const [isPasswordResetOpen, setIsPasswordResetOpen] = useState(false);
    const [banningUser, setBanningUser] = useState<User | null>(null);
    const [isBanConfirmOpen, setIsBanConfirmOpen] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);
    const [isCreateUserOpen, setIsCreateUserOpen] = useState(false);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('cards');

    const createUserForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const totalPermisosCount = useMemo(() => Object.values(grouped_permissions_by_resource).reduce((acc, subgroups) => {
        return acc + Object.values(subgroups).reduce((a, resources) =>
            a + Object.values(resources).reduce((b, perms) => b + perms.length, 0)
            , 0);
    }, 0), [grouped_permissions_by_resource]);

    const sortedModules = useMemo(() => {
        if (!grouped_permissions_by_resource) return [];
        const navOrder = [...mainNavItems, ...adminNavItems()]
            .map((item) => (item.group || item.title || '').toUpperCase())
            .filter(Boolean);
        return Object.entries(grouped_permissions_by_resource).sort(([aKey], [bKey]) => {
            const idxA = navOrder.indexOf(aKey.toUpperCase());
            const idxB = navOrder.indexOf(bKey.toUpperCase());
            if (idxA !== -1 && idxB !== -1) return idxA - idxB;
            if (idxA !== -1) return -1;
            if (idxB !== -1) return 1;
            return aKey.localeCompare(bKey);
        });
    }, [grouped_permissions_by_resource]);

    const stats = useMemo(() => ({
        usuarios: usuarios.length,
        roles: roles.length,
        permisos: totalPermisosCount,
        asignaciones: usuariosRoles.length,
    }), [usuarios, roles, totalPermisosCount, usuariosRoles]);

    const rolForm = useForm({ name: '', permissions: [] as number[] });
    const permisoForm = useForm({ name: '' });

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSearchQuery(e.target.value);
    };

    const clearSearch = () => {
        setSearchQuery('');
        searchRef.current?.focus();
    };

    const toggleGrupo = (key: string) => {
        setGruposExpandidos(prev => ({ ...prev, [key]: !prev[key] }));
    };

    const toggleTodosLosGrupos = (expandir: boolean) => {
        const grupos: Record<string, boolean> = {};
        const recursos: Record<string, boolean> = {};
        if (expandir) {
            Object.keys(grouped_permissions_by_resource).forEach(k => { grupos[k] = true; });
            Object.values(grouped_permissions_by_resource).forEach(subgroups => {
                Object.values(subgroups).forEach(resources => {
                    Object.keys(resources).forEach(r => { recursos[r] = true; });
                });
            });
        }
        setGruposExpandidos(grupos);
        setRecursosExpandidos(recursos);
    };

    const filteredRoles = useMemo(() => {
        return roles.filter(r => r.name.toLowerCase().includes(searchQuery.toLowerCase()));
    }, [roles, searchQuery]);

    const systemRoles = useMemo(() => filteredRoles.filter(r => r.owner_id === null), [filteredRoles]);
    const customRoles = useMemo(() => filteredRoles.filter(r => r.owner_id !== null), [filteredRoles]);

    const getModuleSummary = (selectedIds: number[]): { module: string; count: number; total: number }[] => {
        return Object.entries(grouped_permissions_by_resource).map(([module, subgroups]) => {
            const allPerms = Object.values(subgroups).flatMap(resources => Object.values(resources).flat());
            const count = allPerms.filter(p => selectedIds.includes(p.id)).length;
            return { module, count, total: allPerms.length };
        }).filter(m => m.count > 0);
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const getTotalPermCount = (selectedIds: number[]) => selectedIds.length;

    const handleSaveRole = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingRole) {
            rolForm.put(route('usuarios-roles.role.update', editingRole.id), {
                onSuccess: () => { setIsRolOpen(false); setEditingRole(null); rolForm.reset(); },
                preserveScroll: true,
            });
        } else {
            rolForm.post(route('usuarios-roles.role.store'), {
                onSuccess: () => { setIsRolOpen(false); rolForm.reset(); },
                preserveScroll: true,
            });
        }
    };

    const handleSavePermission = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingPermission) {
            permisoForm.put(route('usuarios-roles.permission.update', editingPermission.id), {
                onSuccess: () => { setIsPermisoOpen(false); setEditingPermission(null); permisoForm.reset(); },
                preserveScroll: true,
            });
        } else {
            permisoForm.post(route('usuarios-roles.permission.store'), {
                onSuccess: () => { setIsPermisoOpen(false); permisoForm.reset(); },
                preserveScroll: true,
            });
        }
    };

    const openEditRole = (role: Role) => {
        setEditingRole(role);
        rolForm.setData({ name: role.name, permissions: role.permissions.map(p => p.id) });
        setGruposExpandidos({});
        setRecursosExpandidos({});
        setIsRolOpen(true);
    };

    const handleAddAsignacion = () => {
        if (!assignUserId || !assignRoleId) return;
        router.post(route('usuarios-roles.store'), {
            usuario_id: assignUserId,
            rol_id: assignRoleId,
        }, {
            onSuccess: () => { setShowAssignModal(false); setAssignUserId(''); setAssignRoleId(''); },
            preserveScroll: true,
        });
    };

    const handleDeleteAsignacion = (id: string) => {
        if (confirm('¿Eliminar esta asignación de rol?')) {
            router.delete(route('usuarios-roles.destroy', id), { preserveScroll: true });
        }
    };

    const handleDeleteRole = (id: number) => {
        if (confirm('¿Eliminar este rol? Los usuarios asignados perderán sus permisos.')) {
            router.delete(route('usuarios-roles.role.destroy', id), { preserveScroll: true });
        }
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleDeletePermission = (id: number) => {
        if (confirm('¿Eliminar este permiso de forma permanente?')) {
            router.delete(route('usuarios-roles.permission.destroy', id), { preserveScroll: true });
        }
    };

    const handleToggleOfficial = (profileId: number) => {
        router.patch(route('usuarios-roles.toggle-official', { publicProfile: profileId }), {}, { preserveScroll: true });
    };

    const handleToggleStatus = (profileId: number, field: 'is_verified' | 'is_official', currentValue: boolean) => {
        router.patch(
            route('usuarios-roles.toggle-status', { publicProfile: profileId }),
            { field, value: !currentValue },
            { preserveScroll: true }
        );
    };

    const usuariosAgrupados = useMemo(() => {
        const map = new Map<number, { user: User; assignments: UsuarioRol[] }>();
        for (const ur of usuariosRoles) {
            const existing = map.get(ur.user_id);
            if (existing) {
                existing.assignments.push(ur);
            } else {
                const user = usuarios.find(u => u.id === ur.user_id);
                if (user) map.set(ur.user_id, { user, assignments: [ur] });
            }
        }
        for (const u of usuarios) {
            if (!map.has(u.id)) map.set(u.id, { user: u, assignments: [] });
        }
        return Array.from(map.values());
    }, [usuariosRoles, usuarios]);

    const filteredUserGroups = useMemo(() => {
        if (!searchQuery) return usuariosAgrupados;
        const q = searchQuery.toLowerCase();
        return usuariosAgrupados.filter(g =>
            g.user.name.toLowerCase().includes(q) ||
            g.user.email.toLowerCase().includes(q) ||
            g.assignments.some(a => a.role_name.toLowerCase().includes(q))
        );
    }, [usuariosAgrupados, searchQuery]);

    const filteredAssignUsers = useMemo(() => {
        const q = userSearch.toLowerCase();
        return usuarios.filter(u => !q || u.name.toLowerCase().includes(q));
    }, [usuarios, userSearch]);

    const filteredAssignRoles = useMemo(() => {
        const q = roleSearch.toLowerCase();
        if (is_master) {
            return roles.filter(r => !q || r.name.toLowerCase().includes(q));
        }
        return roles.filter(r => r.owner_id !== null && (!q || r.name.toLowerCase().includes(q)));
    }, [roles, roleSearch, is_master]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Accesos" />

            <div className="mx-auto flex max-w-7xl flex-col gap-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Gestión de Accesos</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Controla los permisos y roles de tu plataforma
                        </p>
                    </div>
                    {is_master && (
                        <Badge variant="secondary" className="inline-flex w-fit items-center gap-1.5 border-orange-300 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700">
                            <Crown className="h-3.5 w-3.5" /> Control Total
                        </Badge>
                    )}
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    {[
                        { icon: Users, label: 'Usuarios', value: stats.usuarios, color: 'text-blue-600', bg: 'bg-blue-50', masterOnly: true },
                        { icon: Shield, label: 'Roles', value: stats.roles, color: 'text-emerald-600', bg: 'bg-emerald-50', masterOnly: false },
                        { icon: KeyRound, label: 'Permisos', value: stats.permisos, color: 'text-purple-600', bg: 'bg-purple-50', masterOnly: false },
                        { icon: UserCog, label: 'Asignaciones', value: stats.asignaciones, color: 'text-amber-600', bg: 'bg-amber-50', masterOnly: true },
                    ].filter(stat => !stat.masterOnly || canSeeTiendas).map((stat, i) => (
                        <Card key={i} className="border-0 shadow-sm">
                            <CardContent className="flex items-center gap-3 p-4">
                                <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${stat.bg}`}>
                                    <stat.icon className={`h-5 w-5 ${stat.color}`} />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold">{stat.value}</p>
                                    <p className="text-xs text-muted-foreground">{stat.label}</p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Main Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="w-full justify-start overflow-x-auto rounded-xl border bg-muted/50 p-1">
                        {canSeeTiendas && (
                            <TabsTrigger value="asignaciones" className="gap-2 rounded-lg data-[state=active]:shadow-sm">
                                <Users className="h-4 w-4" /> Asignaciones
                            </TabsTrigger>
                        )}
                        <TabsTrigger value="roles" className="gap-2 rounded-lg data-[state=active]:shadow-sm">
                            <Shield className="h-4 w-4" /> Roles
                        </TabsTrigger>
                        <TabsTrigger value="permisos" className="gap-2 rounded-lg data-[state=active]:shadow-sm">
                            <KeyRound className="h-4 w-4" /> Permisos
                        </TabsTrigger>
                        {canSeeTiendas && (
                            <TabsTrigger value="tiendas" className="gap-2 rounded-lg data-[state=active]:shadow-sm">
                                <Store className="h-4 w-4" /> Tiendas
                            </TabsTrigger>
                        )}
                        {is_master && masterData && (
                            <TabsTrigger value="master" className="gap-2 rounded-lg data-[state=active]:shadow-sm">
                                <Globe className="h-4 w-4" /> Panel Master
                            </TabsTrigger>
                        )}
                    </TabsList>

                    {/* === ASIGNACIONES TAB === */}
                    {canSeeTiendas && (
                        <TabsContent value="asignaciones" className="mt-4 space-y-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="relative flex-1 max-w-md">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        ref={searchRef}
                                        placeholder="Buscar usuario o rol..."
                                        className="h-10 rounded-xl pl-9 pr-9"
                                        value={searchQuery}
                                        onChange={handleSearch}
                                    />
                                    {searchQuery && (
                                        <button onClick={clearSearch} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                                            <X className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                                {canCreate && (
                                    <Button onClick={() => setShowAssignModal(true)} className="rounded-xl gap-2">
                                        <UserPlus className="h-4 w-4" /> Asignar Rol
                                    </Button>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pb-2">
                                <Button variant={viewMode === 'table' ? 'default' : 'outline'} size="sm" className="h-8 text-xs" onClick={() => setViewMode('table')}>
                                    <List className="mr-1 h-3.5 w-3.5" /> Tabla
                                </Button>
                                <Button variant={viewMode === 'cards' ? 'default' : 'outline'} size="sm" className="h-8 text-xs" onClick={() => setViewMode('cards')}>
                                    <LayoutGrid className="mr-1 h-3.5 w-3.5" /> Tarjetas
                                </Button>
                            </div>

                            {viewMode === 'cards' ? (
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {filteredUserGroups.map(({ user, assignments }) => {
                                        // eslint-disable-next-line @typescript-eslint/no-unused-vars
                                        const totalPerms = assignments.reduce((acc, a) => acc + a.permissions.length, 0);
                                        return (
                                            <Card key={user.id} className="group overflow-hidden border-0 bg-gradient-to-b from-card to-muted/20 shadow-sm transition-all hover:shadow-md">
                                                <CardHeader className="pb-0">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar className="h-10 w-10 ring-2 ring-background">
                                                                <AvatarImage src={user.profile_photo_url ?? undefined} />
                                                                <AvatarFallback className="bg-primary/10 text-sm font-bold text-primary">
                                                                    {user.name.substring(0, 2).toUpperCase()}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div>
                                                                <CardTitle className="text-sm font-bold">{user.name}</CardTitle>
                                                                <p className="text-xs text-muted-foreground">{user.email}</p>
                                                            </div>
                                                        </div>
                                                        <Badge variant="outline" className="text-[10px] shrink-0">
                                                            {assignments.length} rol{assignments.length !== 1 ? 'es' : ''}
                                                        </Badge>
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="pt-3">
                                                    {assignments.length === 0 ? (
                                                        <div className="rounded-lg border border-dashed p-3 text-center">
                                                            <p className="text-xs text-muted-foreground">Sin roles asignados</p>
                                                            {canCreate && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="mt-1 h-7 text-xs gap-1"
                                                                    onClick={() => { setAssignUserId(String(user.id)); setShowAssignModal(true); }}
                                                                >
                                                                    <Plus className="h-3 w-3" /> Asignar
                                                                </Button>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <div className="space-y-2">
                                                            {assignments.map(ur => {
                                                                const role = roles.find(r => r.name === ur.role_name);
                                                                const lc = role ? getLevelColor(role.level) : null;
                                                                const permCount = ur.permissions.length;
                                                                const permTotal = totalPermisosCount;
                                                                const pct = permTotal > 0 ? Math.round((permCount / permTotal) * 100) : 0;
                                                                const moduleSummary = getModuleSummary(ur.permissions.map(p => p.id));

                                                                return (
                                                                    <div key={ur.id} className="rounded-lg border bg-background p-2.5 transition-all hover:border-primary/30">
                                                                        <div className="flex items-center justify-between gap-2">
                                                                            <div className="flex items-center gap-2 min-w-0">
                                                                                {lc && (
                                                                                    <div className={`h-2 w-2 shrink-0 rounded-full ${lc.text.replace('text-', 'bg-')}`} />
                                                                                )}
                                                                                <span className="truncate text-sm font-semibold">{ur.role_name}</span>
                                                                                {role && (
                                                                                    <Badge variant="outline" className={`text-[9px] px-1.5 py-0 ${lc?.text ?? ''}`}>
                                                                                        Lv.{role.level}
                                                                                    </Badge>
                                                                                )}
                                                                            </div>
                                                                            <div className="flex shrink-0 gap-0.5">
                                                                                <TooltipProvider>
                                                                                    <Tooltip>
                                                                                        <TooltipTrigger asChild>
                                                                                            <Button variant="ghost" size="icon" className="h-6 w-6 text-muted-foreground" onClick={() => role && setViewingRole(role)}>
                                                                                                <Eye className="h-3 w-3" />
                                                                                            </Button>
                                                                                        </TooltipTrigger>
                                                                                        <TooltipContent>Ver permisos</TooltipContent>
                                                                                    </Tooltip>
                                                                                </TooltipProvider>
                                                                                <TooltipProvider>
                                                                                    <Tooltip>
                                                                                        <TooltipTrigger asChild>
                                                                                            <Button variant="ghost" size="icon" className="h-6 w-6 text-destructive" onClick={() => handleDeleteAsignacion(ur.id)}>
                                                                                                <X className="h-3 w-3" />
                                                                                            </Button>
                                                                                        </TooltipTrigger>
                                                                                        <TooltipContent>Quitar rol</TooltipContent>
                                                                                    </Tooltip>
                                                                                </TooltipProvider>
                                                                            </div>
                                                                        </div>
                                                                        <div className="mt-1.5 flex items-center gap-2">
                                                                            <Progress value={pct} className="h-1.5 flex-1" />
                                                                            <span className="shrink-0 text-[10px] text-muted-foreground">{permCount}/{permTotal}</span>
                                                                        </div>
                                                                        <div className="mt-1.5 flex flex-wrap gap-1">
                                                                            {moduleSummary.slice(0, 3).map(m => (
                                                                                <Badge key={m.module} variant="secondary" className="text-[9px] px-1.5 py-0 gap-0.5">
                                                                                    <span>{getModuleIcon(m.module)}</span>
                                                                                    {m.count}
                                                                                </Badge>
                                                                            ))}
                                                                            {moduleSummary.length > 3 && (
                                                                                <Badge variant="outline" className="text-[9px] px-1.5 py-0">
                                                                                    +{moduleSummary.length - 3}
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                            {canCreate && (
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="mt-1 h-7 w-full text-xs gap-1"
                                                                    onClick={() => { setAssignUserId(String(user.id)); setShowAssignModal(true); }}
                                                                >
                                                                    <Plus className="h-3 w-3" /> Agregar Rol
                                                                </Button>
                                                            )}
                                                        </div>
                                                    )}
                                                    <Separator className="my-2" />
                                                    <div className="flex gap-1.5">
                                                        {canEdit && (
                                                            <TooltipProvider>
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button variant="ghost" size="sm" className="h-7 text-xs gap-1" onClick={() => { setEditingUser(user); setIsUserEditOpen(true); }}>
                                                                            <Pencil className="h-3 w-3" /> Editar
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>Editar datos del usuario</TooltipContent>
                                                                </Tooltip>
                                                            </TooltipProvider>
                                                        )}
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="sm" className="h-7 text-xs gap-1" onClick={() => { setResettingPasswordUser(user); setIsPasswordResetOpen(true); }}>
                                                                        <KeyRound className="h-3 w-3" /> Password
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Restablecer contraseña</TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="sm" className={`h-7 text-xs gap-1 ${user.is_active === false ? 'text-green-600' : 'text-destructive'}`} onClick={() => { setBanningUser(user); setIsBanConfirmOpen(true); }}>
                                                                        {user.is_active === false ? <Check className="h-3 w-3" /> : <X className="h-3 w-3" />}
                                                                        {user.is_active === false ? 'Desbloquear' : 'Bloquear'}
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>{user.is_active === false ? 'Reactivar usuario' : 'Bloquear acceso del usuario'}</TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                    {filteredUserGroups.length === 0 && (
                                        <div className="col-span-full flex flex-col items-center py-12 text-muted-foreground">
                                            <Users className="mb-3 h-12 w-12 opacity-20" />
                                            <p className="font-medium">No se encontraron resultados</p>
                                            <p className="text-sm">Intenta con otro término de búsqueda</p>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="px-4 py-3 text-left font-medium">Usuario</th>
                                                <th className="px-4 py-3 text-left font-medium">Email</th>
                                                <th className="px-4 py-3 text-left font-medium">Roles</th>
                                                <th className="px-4 py-3 text-center font-medium">Asignaciones</th>
                                                <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredUserGroups.length === 0 ? (
                                                <tr>
                                                    <td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">
                                                        <Users className="mx-auto mb-3 h-12 w-12 opacity-20" />
                                                        <p className="font-medium">No se encontraron resultados</p>
                                                    </td>
                                                </tr>
                                            ) : filteredUserGroups.map(({ user, assignments }) => (
                                                <tr key={user.id} className="border-b hover:bg-muted/30">
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <Avatar className="h-8 w-8">
                                                                <AvatarImage src={user.profile_photo_url ?? undefined} />
                                                                <AvatarFallback className="bg-primary/10 text-xs font-bold text-primary">
                                                                    {user.name.substring(0, 2).toUpperCase()}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <span className="font-medium">{user.name}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">{user.email}</td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex flex-wrap gap-1">
                                                            {assignments.length === 0 ? (
                                                                <span className="text-xs text-muted-foreground">Sin roles</span>
                                                            ) : assignments.slice(0, 2).map(ur => (
                                                                <Badge key={ur.id} variant="secondary" className="text-[10px]">{ur.role_name}</Badge>
                                                            ))}
                                                            {assignments.length > 2 && (
                                                                <Badge variant="outline" className="text-[10px]">+{assignments.length - 2}</Badge>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-center text-sm">{assignments.length}</td>
                                                    <td className="px-4 py-3 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            {canCreate && (
                                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => { setAssignUserId(String(user.id)); setShowAssignModal(true); }} title="Asignar rol">
                                                                    <Plus className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canEdit && (
                                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => { setEditingUser(user); setIsUserEditOpen(true); }} title="Editar">
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => { setResettingPasswordUser(user); setIsPasswordResetOpen(true); }} title="Reset password">
                                                                <KeyRound className="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="sm" className={`h-8 w-8 p-0 ${user.is_active === false ? 'text-green-600' : 'text-destructive'}`} onClick={() => { setBanningUser(user); setIsBanConfirmOpen(true); }} title={user.is_active === false ? 'Desbloquear' : 'Bloquear'}>
                                                                {user.is_active === false ? <Check className="h-4 w-4" /> : <X className="h-4 w-4" />}
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </TabsContent>
                    )}

                    {/* === ROLES TAB === */}
                    <TabsContent value="roles" className="mt-4 space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1 max-w-md">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Filtrar roles..."
                                    className="h-10 rounded-xl pl-9"
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)}
                                />
                            </div>
                            {canManageRoles && (
                                <Button onClick={() => { setEditingRole(null); rolForm.reset(); setGruposExpandidos({}); setIsRolOpen(true); }} className="rounded-xl gap-2">
                                    <Plus className="h-4 w-4" /> Nuevo Rol
                                </Button>
                            )}
                        </div>

                        {systemRoles.length > 0 && is_master && (
                            <div>
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                                    <Lock className="h-4 w-4" /> Roles del Sistema
                                </h3>
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {systemRoles.map(role => {
                                        const lc = getLevelColor(role.level);
                                        const cnt = role.permissions.length;
                                        const pct = totalPermisosCount > 0 ? Math.round((cnt / totalPermisosCount) * 100) : 0;
                                        const summary = getModuleSummary(role.permissions.map(p => p.id));
                                        return (
                                            <Card key={role.id} className={`overflow-hidden border-l-4 ${lc.border} bg-gradient-to-br ${lc.bg.replace('from-', 'from-').replace('to-', '/5 to-').replace('from-', 'from-')} bg-opacity-5`}>
                                                <CardHeader className="pb-2">
                                                    <div className="flex items-start justify-between">
                                                        <Badge variant="outline" className={`text-[9px] ${lc.text} border-current/30`}>
                                                            {lc.label}
                                                        </Badge>
                                                        <div className="flex gap-0.5">
                                                            <Button variant="ghost" size="icon" className="h-6 w-6" title="Ver" onClick={() => setViewingRole(role)}>
                                                                <Eye className="h-3 w-3" />
                                                            </Button>
                                                            {canEdit && (
                                                                <Button variant="ghost" size="icon" className="h-6 w-6" title="Editar" disabled={!is_master} onClick={() => openEditRole(role)}>
                                                                    <Pencil className="h-3 w-3" />
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button variant="ghost" size="icon" className="h-6 w-6 text-destructive" disabled={!is_master} onClick={() => handleDeleteRole(role.id)}>
                                                                    <Trash2 className="h-3 w-3" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <CardTitle className="text-base font-bold">{role.name}</CardTitle>
                                                </CardHeader>
                                                <CardContent className="pt-0">
                                                    <div className="mb-2 flex items-center gap-2">
                                                        <Progress value={pct} className="h-1.5 flex-1" />
                                                        <span className="text-[10px] text-muted-foreground">{cnt}/{totalPermisosCount}</span>
                                                    </div>
                                                    <div className="flex flex-wrap gap-1">
                                                        {summary.slice(0, 4).map(m => (
                                                            <Badge key={m.module} variant="secondary" className="text-[9px] px-1.5 py-0 gap-0.5">
                                                                <span>{getModuleIcon(m.module)}</span>
                                                                {m.module} ({m.count})
                                                            </Badge>
                                                        ))}
                                                        {summary.length > 4 && (
                                                            <Badge variant="outline" className="text-[9px] px-1.5 py-0">
                                                                +{summary.length - 4}
                                                            </Badge>
                                                        )}
                                                        {summary.length === 0 && (
                                                            <span className="text-[10px] text-muted-foreground">Sin permisos</span>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        <div>
                            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                                <Shield className="h-4 w-4" /> Roles Personalizados
                            </h3>
                            {customRoles.length > 0 ? (
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {customRoles.map(role => {
                                        const lc = getLevelColor(role.level);
                                        const cnt = role.permissions.length;
                                        const pct = totalPermisosCount > 0 ? Math.round((cnt / totalPermisosCount) * 100) : 0;
                                        const summary = getModuleSummary(role.permissions.map(p => p.id));
                                        return (
                                            <Card key={role.id} className="group overflow-hidden border-0 bg-gradient-to-b from-card to-muted/20 shadow-sm transition-all hover:shadow-md">
                                                <CardHeader className="pb-2">
                                                    <div className="flex items-start justify-between">
                                                        <Badge variant="outline" className="text-[9px] gap-1">
                                                            <Tag className="h-2.5 w-2.5" /> Personalizado
                                                        </Badge>
                                                        <div className="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <Button variant="ghost" size="icon" className="h-6 w-6" title="Ver" onClick={() => setViewingRole(role)}>
                                                                <Eye className="h-3 w-3" />
                                                            </Button>
                                                            {canManageRoles && (
                                                                <Button variant="ghost" size="icon" className="h-6 w-6" title="Editar" onClick={() => openEditRole(role)}>
                                                                    <Pencil className="h-3 w-3" />
                                                                </Button>
                                                            )}
                                                            {canManageRoles && (
                                                                <Button variant="ghost" size="icon" className="h-6 w-6 text-destructive" onClick={() => handleDeleteRole(role.id)}>
                                                                    <Trash2 className="h-3 w-3" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <CardTitle className="text-base font-bold">{role.name}</CardTitle>
                                                    {lc && (
                                                        <Badge variant="outline" className={`text-[9px] w-fit ${lc.text} border-current/30`}>
                                                            Nivel {role.level}
                                                        </Badge>
                                                    )}
                                                </CardHeader>
                                                <CardContent className="pt-0">
                                                    <div className="mb-2 flex items-center gap-2">
                                                        <Progress value={pct} className="h-1.5 flex-1" />
                                                        <span className="text-[10px] text-muted-foreground">{cnt}/{totalPermisosCount}</span>
                                                    </div>
                                                    <div className="flex flex-wrap gap-1">
                                                        {summary.slice(0, 4).map(m => (
                                                            <Badge key={m.module} variant="secondary" className="text-[9px] px-1.5 py-0 gap-0.5">
                                                                <span>{getModuleIcon(m.module)}</span>
                                                                {m.module} ({m.count})
                                                            </Badge>
                                                        ))}
                                                        {summary.length > 4 && (
                                                            <Badge variant="outline" className="text-[9px] px-1.5 py-0">
                                                                +{summary.length - 4}
                                                            </Badge>
                                                        )}
                                                        {summary.length === 0 && (
                                                            <span className="text-[10px] text-muted-foreground">Sin permisos</span>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed p-10 text-center">
                                    <Shield className="mx-auto mb-2 h-10 w-10 text-muted-foreground/30" />
                                    <p className="font-medium text-muted-foreground">No hay roles personalizados aún</p>
                                    <p className="mt-1 text-sm text-muted-foreground/60">Crea un nuevo rol para tus usuarios.</p>
                                    {canManageRoles && (
                                        <Button variant="outline" className="mt-4 gap-2" onClick={() => { setEditingRole(null); rolForm.reset(); setIsRolOpen(true); }}>
                                            <Plus className="h-4 w-4" /> Crear Primer Rol
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    </TabsContent>

                    {/* === PERMISOS TAB === */}
                    <TabsContent value="permisos" className="mt-4 space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1 max-w-md">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar permisos en todas las cards..."
                                    className="h-10 rounded-xl pl-9"
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)}
                                />
                                {searchQuery && (
                                    <button
                                        onClick={() => setSearchQuery('')}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                            </div>

                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            {sortedModules.map(([moduleName, subgroups]) => {
                                const allPermsInModule = Object.values(subgroups).flatMap(resources => Object.values(resources).flat());
                                const filteredPermsInModule = allPermsInModule.filter(p =>
                                    !searchQuery ||
                                    p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                    p.friendly_name.toLowerCase().includes(searchQuery.toLowerCase())
                                );
                                if (filteredPermsInModule.length === 0 && searchQuery) return null;

                                return (
                                    <Card key={moduleName} className="flex flex-col overflow-hidden border border-slate-100 shadow-sm bg-white rounded-xl hover:shadow-md transition-shadow">
                                        <CardHeader className="flex items-center justify-between gap-2 px-3 py-2.5 bg-slate-50/50 border-b border-slate-100">
                                            <div className="flex items-center gap-2 min-w-0 flex-1">
                                                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary shrink-0">
                                                    {getModuleIcon(moduleName)}
                                                </div>
                                                <div className="min-w-0">
                                                    <CardTitle className="text-sm font-semibold text-slate-800 truncate">{moduleName}</CardTitle>
                                                    <span className="text-[10px] text-slate-500 font-medium">
                                                        {allPermsInModule.length} permiso{allPermsInModule.length !== 1 ? 's' : ''}
                                                        {searchQuery && ` (filtrados: ${filteredPermsInModule.length})`}
                                                    </span>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="flex-1 min-h-0 p-0">
                                            <div className="max-h-72 overflow-y-auto p-3 custom-scrollbar">
                                                <div className="space-y-3">
                                                    {Object.entries(subgroups).map(([subName, resources]) => {
                                                        const subPerms = Object.values(resources).flat();
                                                        const filteredSubPerms = subPerms.filter(p =>
                                                            !searchQuery ||
                                                            p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                                            p.friendly_name.toLowerCase().includes(searchQuery.toLowerCase())
                                                        );
                                                        if (filteredSubPerms.length === 0) return null;

                                                        return (
                                                            <div key={subName} className="space-y-1.5">
                                                                <div className="flex items-center gap-1.5 px-1 py-1">
                                                                    <span className="text-[10px] font-bold tracking-wider text-slate-400 uppercase flex-1 truncate">
                                                                        {subName}
                                                                    </span>
                                                                    <span className="text-slate-300 font-normal text-[10px]">({filteredSubPerms.length})</span>
                                                                </div>
                                                                <div className="space-y-1 pl-3 border-l-2 border-slate-100">
                                                                    {Object.entries(resources).map(([resName, perms]) => {
                                                                        const filteredPerms = perms.filter(p =>
                                                                            !searchQuery ||
                                                                            p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                                                            p.friendly_name.toLowerCase().includes(searchQuery.toLowerCase())
                                                                        );
                                                                        if (filteredPerms.length === 0) return null;

                                                                        return (
                                                                            <div key={resName} className="space-y-0.5">
                                                                                <div className="text-[9px] font-semibold text-slate-500 uppercase tracking-wide px-1">{resName}</div>
                                                                                {filteredPerms.map(p => (
                                                                                    <div key={p.id} className="flex items-center gap-2 rounded px-2 py-1 text-slate-600">
                                                                                        <div className="h-1 w-1 rounded-full bg-slate-300 shrink-0" />
                                                                                        <div className="flex-1 min-w-0 flex flex-col">
                                                                                            <span className="text-xs font-medium text-slate-700 truncate">{getFriendlyPermissionLabel(p)}</span>
                                                                                            <span className="text-[9px] font-mono text-slate-400 truncate">{p.name}</span>
                                                                                        </div>
                                                                                    </div>
                                                                                ))}
                                                                            </div>
                                                                        );
                                                                    })}
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </TabsContent>

                    {/* === TIENDAS TAB === */}
                    {canSeeTiendas && (
                        <TabsContent value="tiendas" className="mt-4 space-y-4">
                            <div className="relative max-w-md">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar tienda por nombre, slug o dueño..."
                                    className="h-10 rounded-xl pl-9"
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)}
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {publicProfiles
                                    .filter(p =>
                                        p.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                        p.slug.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                        p.user.name.toLowerCase().includes(searchQuery.toLowerCase())
                                    )
                                    .map(profile => (
                                        <Card key={profile.id} className="group overflow-hidden border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-center justify-between gap-2">
                                                    <Badge variant="outline" className="text-[10px]">Tienda</Badge>
                                                    <div className="flex items-center gap-1.5 flex-wrap">
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Switch
                                                                        checked={profile.is_verified}
                                                                        onCheckedChange={() => handleToggleStatus(profile.id, 'is_verified', profile.is_verified)}
                                                                        className="data-[state=checked]:bg-blue-500 data-[state=checked]:border-blue-500"
                                                                        aria-label="Verificado"
                                                                    />
                                                                </TooltipTrigger>
                                                                <TooltipContent side="top" align="center" className="bg-blue-50 text-blue-700 border-blue-200 px-3 py-1.5 text-xs">
                                                                    <div className="flex items-center gap-1.5">
                                                                        <CheckCircle2 className="h-3 w-3 text-blue-500 fill-blue-500" />
                                                                        Verificada
                                                                    </div>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Switch
                                                                        checked={profile.is_official}
                                                                        onCheckedChange={() => handleToggleStatus(profile.id, 'is_official', profile.is_official)}
                                                                        className="data-[state=checked]:bg-amber-500 data-[state=checked]:border-amber-500"
                                                                        aria-label="Tienda Oficial"
                                                                    />
                                                                </TooltipTrigger>
                                                                <TooltipContent side="top" align="center" className="bg-amber-50 text-amber-700 border-amber-200 px-3 py-1.5 text-xs">
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Star className="h-3 w-3 text-amber-500 fill-amber-500" />
                                                                        Tienda Oficial
                                                                    </div>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </div>
                                                </div>
                                                <CardTitle className="mt-2 text-base font-bold truncate">{profile.title}</CardTitle>
                                                <CardDescription className="flex items-center gap-1 text-xs">
                                                    <Users className="h-3 w-3" /> {profile.user.name}
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent className="pt-0 space-y-2">
                                                <div className="text-xs text-muted-foreground font-mono truncate">/{profile.slug}</div>
                                                <div className="flex items-center gap-1.5 flex-wrap">
                                                    {profile.is_verified && (
                                                        <Badge variant="secondary" className="h-5 px-2 py-0 text-[10px] bg-blue-50 text-blue-700 border-blue-200 gap-1">
                                                            <CheckCircle2 className="h-2.5 w-2.5 fill-blue-500 text-blue-500" />
                                                            Verificada
                                                        </Badge>
                                                    )}
                                                    {profile.is_official && (
                                                        <Badge variant="secondary" className="h-5 px-2 py-0 text-[10px] bg-amber-50 text-amber-700 border-amber-200 gap-1">
                                                            <Star className="h-2.5 w-2.5 fill-amber-500 text-amber-500" />
                                                            Oficial
                                                        </Badge>
                                                    )}
                                                    {!profile.is_verified && !profile.is_official && (
                                                        <Badge variant="outline" className="h-5 px-2 py-0 text-[10px] text-muted-foreground">
                                                            Sin insignias
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                            </div>
                        </TabsContent>
                    )}

                    {/* === PANEL MASTER TAB === */}
                    {is_master && masterData && (
                        <TabsContent value="master" className="mt-4 space-y-6">
                            {/* Stats: new users */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <Card className="border-l-4 border-l-blue-500 shadow-sm">
                                    <CardContent className="flex items-center gap-4 p-5">
                                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50">
                                            <CalendarDays className="h-7 w-7 text-blue-600" />
                                        </div>
                                        <div>
                                            <p className="text-3xl font-bold text-blue-700">{masterData.new_users_7days_count}</p>
                                            <p className="text-sm font-medium text-muted-foreground">Nuevos usuarios (últimos 7 días)</p>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="border-l-4 border-l-emerald-500 shadow-sm">
                                    <CardContent className="flex items-center gap-4 p-5">
                                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50">
                                            <CalendarDays className="h-7 w-7 text-emerald-600" />
                                        </div>
                                        <div>
                                            <p className="text-3xl font-bold text-emerald-700">{masterData.new_users_30days_count}</p>
                                            <p className="text-sm font-medium text-muted-foreground">Nuevos usuarios (últimos 30 días)</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Search + Create */}
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="relative flex-1 max-w-md">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar usuario en toda la plataforma..."
                                        className="h-10 rounded-xl pl-9 pr-9"
                                        value={searchQuery}
                                        onChange={handleSearch}
                                    />
                                    {searchQuery && (
                                        <button onClick={clearSearch} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                                            <X className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                                <Button onClick={() => { createUserForm.reset(); setIsCreateUserOpen(true); }} className="rounded-xl gap-2">
                                    <UserPlus className="h-4 w-4" /> Crear Usuario
                                </Button>
                            </div>

                            {/* All users grid */}
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {(searchQuery
                                    ? masterData.all_users.filter(u =>
                                        u.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                        u.email.toLowerCase().includes(searchQuery.toLowerCase())
                                    )
                                    : masterData.all_users
                                ).map(user => {
                                    const userRoles = roles.filter(r => usuariosRoles.some(ur => ur.user_id === user.id && ur.role_name === r.name));
                                    const levelColor = userRoles.length > 0 ? getLevelColor(Math.min(...userRoles.map(r => r.level))) : null;
                                    return (
                                        <Card key={user.id} className="group overflow-hidden border-0 bg-gradient-to-b from-card to-muted/20 shadow-sm transition-all hover:shadow-md">
                                            <CardHeader className="pb-2">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="h-10 w-10 ring-2 ring-background">
                                                            <AvatarImage src={user.profile_photo_url ?? undefined} />
                                                            <AvatarFallback className="bg-primary/10 text-sm font-bold text-primary">
                                                                {user.name.substring(0, 2).toUpperCase()}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="min-w-0">
                                                            <CardTitle className="text-sm font-bold truncate">{user.name}</CardTitle>
                                                            <p className="text-xs text-muted-foreground truncate">{user.email}</p>
                                                        </div>
                                                    </div>
                                                    <Badge variant="outline" className="text-[9px] shrink-0">
                                                        Owner #{user.owner_id ?? '—'}
                                                    </Badge>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="pt-1">
                                                <div className="flex flex-wrap items-center gap-1.5 mb-2">
                                                    {userRoles.length > 0 ? userRoles.slice(0, 3).map(r => (
                                                        <Badge key={r.id} variant="secondary" className={`text-[9px] ${levelColor?.text ?? ''}`}>
                                                            {r.name}
                                                        </Badge>
                                                    )) : (
                                                        <span className="text-[10px] text-muted-foreground">Sin roles</span>
                                                    )}
                                                    {userRoles.length > 3 && (
                                                        <Badge variant="outline" className="text-[9px]">+{userRoles.length - 3}</Badge>
                                                    )}
                                                </div>
                                                {user.created_at && (
                                                    <p className="text-[10px] text-muted-foreground mb-2">
                                                        Registrado: {new Date(user.created_at).toLocaleDateString(currency.locale, { year: 'numeric', month: 'short', day: 'numeric' })}
                                                    </p>
                                                )}
                                                {user.banned_at && (
                                                    <Badge variant="destructive" className="text-[9px] mb-2">Bloqueado</Badge>
                                                )}
                                                <Separator className="my-2" />
                                                <div className="flex gap-1.5 flex-wrap">
                                                    {canEdit && (
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="sm" className="h-7 text-xs gap-1" onClick={() => { setEditingUser(user); setIsUserEditOpen(true); }}>
                                                                        <Pencil className="h-3 w-3" /> Editar
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Editar datos del usuario</TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    )}
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" className="h-7 text-xs gap-1" onClick={() => { setResettingPasswordUser(user); setIsPasswordResetOpen(true); }}>
                                                                    <KeyRound className="h-3 w-3" /> Password
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>Restablecer contraseña</TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" className={`h-7 text-xs gap-1 ${user.is_active === false ? 'text-green-600' : 'text-destructive'}`} onClick={() => { setBanningUser(user); setIsBanConfirmOpen(true); }}>
                                                                    {user.is_active === false ? <Check className="h-3 w-3" /> : <X className="h-3 w-3" />}
                                                                    {user.is_active === false ? 'Desbloquear' : 'Bloquear'}
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>{user.is_active === false ? 'Reactivar usuario' : 'Bloquear acceso del usuario'}</TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                                {(!searchQuery && masterData.all_users.length === 0) && (
                                    <div className="col-span-full flex flex-col items-center py-12 text-muted-foreground">
                                        <Users className="mb-3 h-12 w-12 opacity-20" />
                                        <p className="font-medium">No hay usuarios registrados</p>
                                    </div>
                                )}
                                {(searchQuery && masterData.all_users.filter(u =>
                                    u.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                    u.email.toLowerCase().includes(searchQuery.toLowerCase())
                                ).length === 0) && (
                                        <div className="col-span-full flex flex-col items-center py-12 text-muted-foreground">
                                            <Users className="mb-3 h-12 w-12 opacity-20" />
                                            <p className="font-medium">No se encontraron resultados</p>
                                            <p className="text-sm">Intenta con otro término de búsqueda</p>
                                        </div>
                                    )}
                            </div>
                        </TabsContent>
                    )}
                </Tabs>
            </div>

            {/* === MODAL: ASIGNAR ROL === */}
            <Dialog open={showAssignModal} onOpenChange={(open) => { setShowAssignModal(open); if (!open) { setUserSearch(''); setRoleSearch(''); } }}>
                <DialogContent className="sm:max-w-[500px] p-4 sm:p-6 gap-0">
                    <DialogHeader className="pb-4">
                        <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                            <UserPlus className="h-5 w-5 text-primary" />
                            Asignar Rol
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Selecciona un usuario y el rol que deseas asignarle.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-5">
                        <div className="space-y-2">
                            <Label className="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Usuario</Label>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                                <Input
                                    placeholder="Buscar usuario..."
                                    className="h-11 rounded-xl pl-9 pr-4"
                                    value={userSearch}
                                    onChange={e => { setUserSearch(e.target.value); setAssignUserId(''); }}
                                />
                            </div>
                            <div className="max-h-48 overflow-y-auto rounded-xl border">
                                {filteredAssignUsers.length === 0 ? (
                                    <div className="flex items-center justify-center py-6 text-sm text-muted-foreground">
                                        No se encontraron usuarios
                                    </div>
                                ) : (
                                    filteredAssignUsers.map(u => (
                                        <button
                                            key={u.id}
                                            type="button"
                                            onClick={() => setAssignUserId(String(u.id))}
                                            className={`flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted/60 ${assignUserId === String(u.id) ? 'bg-primary/10 font-medium' : ''
                                                }`}
                                        >
                                            <Avatar className="h-7 w-7 shrink-0">
                                                <AvatarFallback className="text-[9px]">{u.name.substring(0, 2).toUpperCase()}</AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <span className="truncate block">{u.name}</span>
                                                <span className="truncate block text-xs text-muted-foreground">{u.email}</span>
                                            </div>
                                            {assignUserId === String(u.id) && (
                                                <Check className="h-4 w-4 shrink-0 text-primary" />
                                            )}
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label className="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Rol</Label>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                                <Input
                                    placeholder="Buscar rol..."
                                    className="h-11 rounded-xl pl-9 pr-4"
                                    value={roleSearch}
                                    onChange={e => { setRoleSearch(e.target.value); setAssignRoleId(''); }}
                                />
                            </div>
                            <div className="max-h-48 overflow-y-auto rounded-xl border">
                                {filteredAssignRoles.length === 0 ? (
                                    <div className="flex items-center justify-center py-6 text-sm text-muted-foreground">
                                        No se encontraron roles
                                    </div>
                                ) : (
                                    filteredAssignRoles.map(r => {
                                        const lc = getLevelColor(r.level);
                                        return (
                                            <button
                                                key={r.id}
                                                type="button"
                                                onClick={() => setAssignRoleId(String(r.id))}
                                                className={`flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted/60 ${assignRoleId === String(r.id) ? 'bg-primary/10 font-medium' : ''
                                                    }`}
                                            >
                                                <div className={`h-2.5 w-2.5 shrink-0 rounded-full ${lc.text.replace('text-', 'bg-')}`} />
                                                <div className="min-w-0 flex-1">
                                                    <span className="truncate block">{r.name}</span>
                                                    <span className="truncate block text-xs text-muted-foreground">{lc.label}</span>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1.5">
                                                    {r.owner_id === null && (
                                                        <Lock className="h-3 w-3 text-muted-foreground/40" />
                                                    )}
                                                    {assignRoleId === String(r.id) && (
                                                        <Check className="h-4 w-4 shrink-0 text-primary" />
                                                    )}
                                                </div>
                                            </button>
                                        );
                                    })
                                )}
                            </div>
                        </div>

                        <DialogFooter className="pt-1">
                            <Button onClick={handleAddAsignacion} disabled={!assignUserId || !assignRoleId} className="w-full rounded-xl h-11 text-sm font-semibold">
                                Confirmar Asignación
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            {/* === MODAL: EDITAR USUARIO === */}
            <Dialog open={isUserEditOpen} onOpenChange={setIsUserEditOpen}>
                <DialogContent className="sm:max-w-[425px] p-0 gap-0">
                    <div className="px-4 sm:px-6 pt-4 sm:pt-6">
                        <DialogHeader className="p-0">
                            <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                                <Pencil className="h-5 w-5 text-primary" />
                                Editar Usuario
                            </DialogTitle>
                            <DialogDescription>
                                Modifica los datos del usuario seleccionado.
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        if (!editingUser) return;
                        router.patch(route('usuarios-roles.user.update', editingUser.id), {
                            name: (document.getElementById('edit-user-name') as HTMLInputElement)?.value,
                            email: (document.getElementById('edit-user-email') as HTMLInputElement)?.value,
                            rut: (document.getElementById('edit-user-rut') as HTMLInputElement)?.value,
                            telefono: (document.getElementById('edit-user-telefono') as HTMLInputElement)?.value,
                            direccion: (document.getElementById('edit-user-direccion') as HTMLInputElement)?.value,
                        }, {
                            preserveScroll: true,
                            onSuccess: () => { setIsUserEditOpen(false); setEditingUser(null); },
                        });
                    }} className="space-y-4 px-4 sm:px-6 py-4">
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Nombre</Label>
                            <Input id="edit-user-name" defaultValue={editingUser?.name} className="h-10 rounded-xl" required />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Email</Label>
                            <Input id="edit-user-email" type="email" defaultValue={editingUser?.email} className="h-10 rounded-xl" required />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">RUT</Label>
                            <Input id="edit-user-rut" defaultValue={editingUser?.rut ?? ''} className="h-10 rounded-xl" />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Teléfono</Label>
                            <Input id="edit-user-telefono" defaultValue={editingUser?.telefono ?? ''} className="h-10 rounded-xl" />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Dirección</Label>
                            <Input id="edit-user-direccion" defaultValue={editingUser?.direccion ?? ''} className="h-10 rounded-xl" />
                        </div>
                        <DialogFooter className="p-0">
                            <Button type="submit" className="w-full rounded-xl">
                                Guardar Cambios
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* === MODAL: RESTABLECER CONTRASEÑA === */}
            <Dialog open={isPasswordResetOpen} onOpenChange={setIsPasswordResetOpen}>
                <DialogContent className="sm:max-w-[425px] p-0 gap-0">
                    <div className="px-4 sm:px-6 pt-4 sm:pt-6">
                        <DialogHeader className="p-0">
                            <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                                <KeyRound className="h-5 w-5 text-primary" />
                                Restablecer Contraseña
                            </DialogTitle>
                            <DialogDescription>
                                Ingresa la nueva contraseña para <strong>{resettingPasswordUser?.name}</strong>.
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        if (!resettingPasswordUser) return;
                        const password = (document.getElementById('reset-password') as HTMLInputElement)?.value;
                        const passwordConfirmation = (document.getElementById('reset-password-confirmation') as HTMLInputElement)?.value;
                        router.post(route('usuarios-roles.user.reset-password', resettingPasswordUser.id), {
                            password,
                            password_confirmation: passwordConfirmation,
                        }, {
                            preserveScroll: true,
                            onSuccess: () => { setIsPasswordResetOpen(false); setResettingPasswordUser(null); },
                        });
                    }} className="space-y-4 px-4 sm:px-6 py-4">
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Nueva Contraseña</Label>
                            <Input id="reset-password" type="password" className="h-10 rounded-xl" required minLength={8} />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Confirmar Contraseña</Label>
                            <Input id="reset-password-confirmation" type="password" className="h-10 rounded-xl" required minLength={8} />
                        </div>
                        <DialogFooter className="p-0">
                            <Button type="submit" className="w-full rounded-xl">
                                Restablecer
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* === MODAL: CONFIRMAR BLOQUEO/DESBLOQUEO === */}
            <Dialog open={isBanConfirmOpen} onOpenChange={setIsBanConfirmOpen}>
                <DialogContent className="sm:max-w-[425px] p-0 gap-0">
                    <div className="px-4 sm:px-6 pt-4 sm:pt-6">
                        <DialogHeader className="p-0">
                            <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                                <AlertTriangle className={`h-5 w-5 ${banningUser?.is_active === false ? 'text-green-600' : 'text-destructive'}`} />
                                {banningUser?.is_active === false ? 'Desbloquear Usuario' : 'Bloquear Usuario'}
                            </DialogTitle>
                            <DialogDescription>
                                {banningUser?.is_active === false
                                    ? `¿Estás seguro de que deseas reactivar a ${banningUser?.name}? Podrá acceder al sistema nuevamente.`
                                    : `¿Estás seguro de que deseas bloquear a ${banningUser?.name}? No podrá acceder al sistema hasta que sea desbloqueado.`
                                }
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <DialogFooter className="px-4 sm:px-6 pb-4 sm:pb-6 pt-2 gap-2">
                        <Button variant="outline" className="rounded-xl flex-1 sm:flex-none" onClick={() => { setIsBanConfirmOpen(false); setBanningUser(null); }}>
                            Cancelar
                        </Button>
                        <Button
                            className={`rounded-xl flex-1 sm:flex-none ${banningUser?.is_active === false ? 'bg-green-600 hover:bg-green-700' : 'bg-destructive hover:bg-destructive/90'}`}
                            onClick={() => {
                                if (!banningUser) return;
                                router.post(route('usuarios-roles.user.toggle-ban', banningUser.id), {}, {
                                    preserveScroll: true,
                                    onSuccess: () => { setIsBanConfirmOpen(false); setBanningUser(null); },
                                });
                            }}
                        >
                            {banningUser?.is_active === false ? 'Sí, Desbloquear' : 'Sí, Bloquear'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* === MODAL: CREAR USUARIO (MASTER) === */}
            <Dialog open={isCreateUserOpen} onOpenChange={setIsCreateUserOpen}>
                <DialogContent className="sm:max-w-[425px] p-0 gap-0">
                    <div className="px-4 sm:px-6 pt-4 sm:pt-6">
                        <DialogHeader className="p-0">
                            <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                                <UserPlus className="h-5 w-5 text-primary" />
                                Crear Usuario
                            </DialogTitle>
                            <DialogDescription>
                                Crea un nuevo usuario en la plataforma.
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        createUserForm.post(route('usuarios-roles.user.store'), {
                            preserveScroll: true,
                            onSuccess: () => { setIsCreateUserOpen(false); createUserForm.reset(); },
                        });
                    }} className="space-y-4 px-4 sm:px-6 py-4">
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Nombre</Label>
                            <Input
                                placeholder="Nombre completo"
                                value={createUserForm.data.name}
                                onChange={e => createUserForm.setData('name', e.target.value)}
                                className="h-10 rounded-xl"
                                required
                            />
                            <InputError message={createUserForm.errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Email</Label>
                            <Input
                                type="email"
                                placeholder="correo@ejemplo.com"
                                value={createUserForm.data.email}
                                onChange={e => createUserForm.setData('email', e.target.value)}
                                className="h-10 rounded-xl"
                                required
                            />
                            <InputError message={createUserForm.errors.email} />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Contraseña</Label>
                            <Input
                                type="password"
                                placeholder="Mínimo 8 caracteres"
                                value={createUserForm.data.password}
                                onChange={e => createUserForm.setData('password', e.target.value)}
                                className="h-10 rounded-xl"
                                required
                                minLength={8}
                            />
                            <InputError message={createUserForm.errors.password} />
                        </div>
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Confirmar Contraseña</Label>
                            <Input
                                type="password"
                                placeholder="Repite la contraseña"
                                value={createUserForm.data.password_confirmation}
                                onChange={e => createUserForm.setData('password_confirmation', e.target.value)}
                                className="h-10 rounded-xl"
                                required
                                minLength={8}
                            />
                        </div>
                        <DialogFooter className="p-0">
                            <Button type="submit" className="w-full rounded-xl" disabled={createUserForm.processing}>
                                {createUserForm.processing ? 'Creando...' : 'Crear Usuario'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* === MODAL: CREAR/EDITAR ROL === */}
            <Dialog open={isRolOpen} onOpenChange={setIsRolOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-full flex-col overflow-hidden rounded-xl p-0 sm:max-w-lg md:max-w-2xl lg:max-w-3xl">
                    <DialogHeader className="border-b px-6 py-4">
                        <DialogTitle className="flex items-center gap-2 text-lg font-bold">
                            <Shield className="h-5 w-5 text-primary" />
                            {editingRole ? 'Editar Rol' : 'Crear Nuevo Rol'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingRole
                                ? 'Actualiza el nombre y los permisos del rol.'
                                : 'Define un nombre y asigna los permisos iniciales.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSaveRole} className="flex flex-col overflow-hidden">
                        <div className="flex-1 overflow-y-auto px-6 py-4">
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">Nombre del Rol</Label>
                                    <Input
                                        placeholder="Ej: Supervisor de Ventas"
                                        value={rolForm.data.name}
                                        onChange={e => rolForm.setData('name', e.target.value)}
                                        required
                                        className="h-10 rounded-xl"
                                    />
                                    <InputError message={rolForm.errors.name} />
                                </div>

                                <Separator />

                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">Permisos</Label>
                                        <div className="flex items-center gap-3 text-xs">
                                            <span className="text-muted-foreground">
                                                {rolForm.data.permissions.length} / {totalPermisosCount} seleccionados
                                            </span>
                                            <button type="button" onClick={() => toggleTodosLosGrupos(true)} className="text-primary hover:underline">Expandir Todo</button>
                                            <span className="text-muted-foreground">|</span>
                                            <button type="button" onClick={() => toggleTodosLosGrupos(false)} className="text-primary hover:underline">Colapsar Todo</button>
                                            <span className="text-muted-foreground">|</span>
                                            <button type="button" onClick={() => {
                                                const allIds = Object.values(grouped_permissions_by_resource).flatMap(subgroups =>
                                                    Object.values(subgroups).flatMap(resources =>
                                                        Object.values(resources).flatMap(perms => perms.map(p => p.id))
                                                    )
                                                );
                                                rolForm.setData('permissions', allIds);
                                            }} className="text-primary hover:underline font-semibold">Seleccionar todo</button>
                                            <span className="text-muted-foreground">|</span>
                                            <button type="button" onClick={() => {
                                                rolForm.setData('permissions', []);
                                            }} className="text-destructive hover:underline font-semibold">Deseleccionar todo</button>
                                        </div>
                                    </div>
                                    <Progress
                                        value={totalPermisosCount > 0 ? (rolForm.data.permissions.length / totalPermisosCount) * 100 : 0}
                                        className="h-1.5"
                                    />
                                </div>

                                <div className="max-h-[35vh] sm:max-h-[45vh] space-y-2 overflow-y-auto rounded-xl border p-3">
                                    {sortedModules.map(([moduleName, subgroups]) => {
                                        const modulePerms = Object.values(subgroups).flatMap(resources =>
                                            Object.values(resources).flat()
                                        );
                                        const allSelected = modulePerms.length > 0 && modulePerms.every(p => rolForm.data.permissions.includes(p.id));
                                        const moduleCount = modulePerms.filter(p => rolForm.data.permissions.includes(p.id)).length;
                                        const isExpanded = gruposExpandidos[moduleName];

                                        return (
                                            <div key={moduleName} className="rounded-lg border bg-muted/20 p-3 transition-all hover:border-primary/20">
                                                <div className="flex items-center justify-between">
                                                    <button type="button" onClick={() => toggleGrupo(moduleName)} className="flex items-center gap-2 text-sm font-bold tracking-wide text-foreground">
                                                        <span className="text-lg">{getModuleIcon(moduleName)}</span>
                                                        <span>{moduleName}</span>
                                                        <span className="font-normal text-muted-foreground">({moduleCount}/{modulePerms.length})</span>
                                                        {isExpanded ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
                                                    </button>
                                                    <div className="flex items-center gap-2">
                                                        <Progress
                                                            value={modulePerms.length > 0 ? (moduleCount / modulePerms.length) * 100 : 0}
                                                            className="h-1.5 w-16"
                                                        />
                                                        <Checkbox
                                                            checked={allSelected}
                                                            onCheckedChange={(checked) => {
                                                                const ids = modulePerms.map(p => p.id);
                                                                if (checked) {
                                                                    rolForm.setData('permissions', [...new Set([...rolForm.data.permissions, ...ids])]);
                                                                } else {
                                                                    rolForm.setData('permissions', rolForm.data.permissions.filter(id => !ids.includes(id)));
                                                                }
                                                            }}
                                                        />
                                                    </div>
                                                </div>

                                                {isExpanded && (
                                                    <div className="mt-3 space-y-3 pl-6">
                                                        {Object.entries(subgroups).map(([subName, resources]) => {
                                                            const subPerms = Object.values(resources).flat();
                                                            const subCount = subPerms.filter(p => rolForm.data.permissions.includes(p.id)).length;
                                                            const subAllSelected = subPerms.length > 0 && subPerms.every(p => rolForm.data.permissions.includes(p.id));
                                                            const subSomeSelected = subPerms.some(p => rolForm.data.permissions.includes(p.id));

                                                            return (
                                                                <div key={subName} className="space-y-2">
                                                                    <div className="flex items-center justify-between gap-2 rounded-md bg-slate-50/80 px-3 py-1.5">
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-[11px] font-bold tracking-wide text-slate-500 uppercase">{subName}</span>
                                                                            <span className="text-[10px] text-slate-400 font-medium">({subCount}/{subPerms.length})</span>
                                                                        </div>
                                                                        <div className="flex items-center gap-1.5">
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => {
                                                                                    const ids = subPerms.map(p => p.id);
                                                                                    rolForm.setData('permissions', [...new Set([...rolForm.data.permissions, ...ids])]);
                                                                                }}
                                                                                className="text-[10px] text-primary hover:underline"
                                                                            >
                                                                                Todos
                                                                            </button>
                                                                            <span className="text-slate-300 text-[10px]">|</span>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => {
                                                                                    const ids = subPerms.map(p => p.id);
                                                                                    rolForm.setData('permissions', rolForm.data.permissions.filter(id => !ids.includes(id)));
                                                                                }}
                                                                                className="text-[10px] text-destructive hover:underline"
                                                                            >
                                                                                Ninguno
                                                                            </button>
                                            <Checkbox
                                                checked={subSomeSelected && !subAllSelected ? 'indeterminate' : subAllSelected}
                                                onCheckedChange={(checked) => {
                                                                                    const ids = subPerms.map(p => p.id);
                                                                                    if (checked) {
                                                                                        rolForm.setData('permissions', [...new Set([...rolForm.data.permissions, ...ids])]);
                                                                                    } else {
                                                                                        rolForm.setData('permissions', rolForm.data.permissions.filter(id => !ids.includes(id)));
                                                                                    }
                                                                                }}
                                                                                className="h-3.5 w-3.5"
                                                                            />
                                                                        </div>
                                                                    </div>
                                                                    <div className="space-y-1.5 pl-3 border-l-2 border-slate-100 ml-2">
                                                                        {Object.entries(resources).map(([resourceName, perms]) => {
                                                                            const resourceKey = `${moduleName}:${subName}:${resourceName}`;
                                                                            const resExpanded = recursosExpandidos[resourceKey];
                                                                            const resAllSelected = perms.every(p => rolForm.data.permissions.includes(p.id));
                                                                            const resCount = perms.filter(p => rolForm.data.permissions.includes(p.id)).length;

                                                                            return (
                                                                                <div key={resourceKey} className="rounded-lg border bg-background overflow-hidden">
                                                                                    <div className="flex items-center justify-between px-3 py-2">
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={() => setRecursosExpandidos(prev => ({ ...prev, [resourceKey]: !prev[resourceKey] }))}
                                                                                            className="flex items-center gap-2 text-xs font-semibold text-foreground"
                                                                                        >
                                                                                            {resExpanded ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                                                                                            <span>{resourceName}</span>
                                                                                            <span className="font-normal text-muted-foreground">({resCount}/{perms.length})</span>
                                                                                        </button>
                                                                                        <div className="flex items-center gap-2">
                                                                                            <button
                                                                                                type="button"
                                                                                                onClick={(e) => {
                                                                                                    e.stopPropagation();
                                                                                                    const ids = perms.map(p => p.id);
                                                                                                    rolForm.setData('permissions', [...new Set([...rolForm.data.permissions, ...ids])]);
                                                                                                }}
                                                                                                className="text-[10px] text-primary hover:underline"
                                                                                            >
                                                                                                Marcar Todo
                                                                                            </button>
                                                                                            <span className="text-muted-foreground text-[10px]">|</span>
                                                                                            <button
                                                                                                type="button"
                                                                                                onClick={(e) => {
                                                                                                    e.stopPropagation();
                                                                                                    const ids = perms.map(p => p.id);
                                                                                                    rolForm.setData('permissions', rolForm.data.permissions.filter(id => !ids.includes(id)));
                                                                                                }}
                                                                                                className="text-[10px] text-destructive hover:underline"
                                                                                            >
                                                                                                Desmarcar
                                                                                            </button>
                                                                                            <Checkbox
                                                                                                checked={resAllSelected}
                                                                                                onCheckedChange={(checked) => {
                                                                                                    const ids = perms.map(p => p.id);
                                                                                                    if (checked) {
                                                                                                        rolForm.setData('permissions', [...new Set([...rolForm.data.permissions, ...ids])]);
                                                                                                    } else {
                                                                                                        rolForm.setData('permissions', rolForm.data.permissions.filter(id => !ids.includes(id)));
                                                                                                    }
                                                                                                }}
                                                                                            />
                                                                                        </div>
                                                                                    </div>

                                                                                    {resExpanded && (
                                                                                        <div className="grid gap-x-4 gap-y-1 border-t bg-muted/10 px-3 py-2 sm:grid-cols-2">
                                                                                            {perms.map(p => (
                                                                                                <div key={p.id} className="flex items-center gap-2 rounded px-1.5 py-0.5 hover:bg-muted/30">
                                                                                                    <Checkbox
                                                                                                        id={`rol-p-${p.id}`}
                                                                                                        checked={rolForm.data.permissions.includes(p.id)}
                                                                                                        onCheckedChange={(checked) => {
                                                                                                            if (checked) {
                                                                                                                rolForm.setData('permissions', [...rolForm.data.permissions, p.id]);
                                                                                                            } else {
                                                                                                                rolForm.setData('permissions', rolForm.data.permissions.filter(id => id !== p.id));
                                                                                                            }
                                                                                                        }}
                                                                                                    />
                                                                                                    <label htmlFor={`rol-p-${p.id}`} className="flex-1 cursor-pointer text-xs">
                                                                                                        <span className="block">{p.friendly_name}</span>
                                                                                                        <span className="block text-[10px] text-muted-foreground font-mono">{p.name}</span>
                                                                                                    </label>
                                                                                                </div>
                                                                                            ))}
                                                                                        </div>
                                                                                    )}
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                        <DialogFooter className="shrink-0 border-t px-6 py-4">
                            <Button type="submit" className="w-full sm:w-auto rounded-xl" disabled={rolForm.processing}>
                                {editingRole ? 'Guardar Cambios' : 'Crear Rol'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* === MODAL: NUEVO/EDITAR PERMISO === */}
            <Dialog open={isPermisoOpen} onOpenChange={setIsPermisoOpen}>
                <DialogContent className="max-w-full sm:max-w-[400px] md:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                            <KeyRound className="h-5 w-5 text-primary" />
                            {editingPermission ? 'Editar Permiso' : 'Nuevo Permiso'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingPermission ? 'Actualiza el nombre del permiso.' : 'Agrega una nueva capacidad al sistema.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSavePermission} className="space-y-4 pt-2">
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Nombre del Permiso</Label>
                            <Input
                                placeholder="Ej: modulo.accion"
                                value={permisoForm.data.name}
                                onChange={e => permisoForm.setData('name', e.target.value)}
                                required
                                className="h-11 rounded-xl"
                            />
                            <InputError message={permisoForm.errors.name} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" className="w-full sm:w-auto rounded-xl" disabled={permisoForm.processing}>
                                {editingPermission ? 'Guardar Cambios' : 'Registrar Permiso'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* === MODAL: VER DETALLE DE ROL === */}
            <Dialog open={!!viewingRole} onOpenChange={() => setViewingRole(null)}>
                <DialogContent className="max-w-full mx-2 w-[95vw] rounded-xl p-4 sm:max-w-lg md:max-w-xl lg:max-w-2xl">
                    <DialogHeader className="pb-0">
                        <div className="flex items-start gap-3 sm:items-center">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                <Shield className="h-5 w-5 text-primary" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <DialogTitle className="text-lg font-bold sm:text-xl truncate">{viewingRole?.name}</DialogTitle>
                                <DialogDescription className="text-xs sm:text-sm">
                                    {viewingRole?.permissions.length ?? 0} permisos de {totalPermisosCount} totales
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    <div className="max-h-[70vh] sm:max-h-[60vh] space-y-3 overflow-y-auto px-1 sm:px-0">
                        {viewingRole && (() => {
                            const permIds = viewingRole.permissions.map(p => p.id);
                            const navOrder = [...mainNavItems, ...adminNavItems()]
                                .map((item) => (item.group || item.title || '').toUpperCase())
                                .filter(Boolean);
                            const filtered = Object.entries(grouped_permissions_by_resource)
                                .map(([moduleName, subgroups]) => {
                                    const filteredSubs: Record<string, Record<string, { id: number; name: string; friendly_name: string }[]>> = {};
                                    for (const [subName, resources] of Object.entries(subgroups)) {
                                        const filteredResources: Record<string, { id: number; name: string; friendly_name: string }[]> = {};
                                        for (const [resName, perms] of Object.entries(resources)) {
                                            const filtered = perms.filter(p => permIds.includes(p.id));
                                            if (filtered.length > 0) filteredResources[resName] = filtered;
                                        }
                                        if (Object.keys(filteredResources).length > 0) filteredSubs[subName] = filteredResources;
                                    }
                                    return { moduleName, subgroups: filteredSubs };
                                })
                                .filter(m => Object.keys(m.subgroups).length > 0)
                                .sort((a, b) => {
                                    const idxA = navOrder.indexOf(a.moduleName.toUpperCase());
                                    const idxB = navOrder.indexOf(b.moduleName.toUpperCase());
                                    if (idxA !== -1 && idxB !== -1) return idxA - idxB;
                                    if (idxA !== -1) return -1;
                                    if (idxB !== -1) return 1;
                                    return a.moduleName.localeCompare(b.moduleName);
                                });

                            return filtered.map(({ moduleName, subgroups }) => {
                                const totalModule = Object.values(subgroups).flatMap(resources => Object.values(resources).flat()).length;
                                return (
                                    <div key={moduleName} className="rounded-xl border bg-muted/20 p-3 sm:p-4">
                                        <div className="mb-3 flex items-center gap-2">
                                            <span className="text-lg">{getModuleIcon(moduleName)}</span>
                                            <span className="text-xs font-bold uppercase">{moduleName}</span>
                                            <Badge variant="secondary" className="ml-auto text-[10px] px-2">{totalModule}</Badge>
                                        </div>
                                        <div className="space-y-3">
                                            {Object.entries(subgroups).map(([subName, resources]) => {
                                                const subTotal = Object.values(resources).flat().length;
                                                return (
                                                    <div key={subName}>
                                                        <div className="mb-1.5 flex items-center gap-1.5">
                                                            <span className="text-[10px] font-bold tracking-wider text-slate-400 uppercase">{subName}</span>
                                                            <span className="text-[9px] text-slate-300">({subTotal})</span>
                                                        </div>
                                                        <div className="space-y-1.5 pl-3 border-l-2 border-slate-100">
                                                            {Object.entries(resources).map(([resName, perms]) => (
                                                                <div key={resName} className="rounded-lg bg-background p-2.5">
                                                                    <h4 className="mb-1.5 text-[10px] font-semibold text-muted-foreground uppercase">{resName}</h4>
                                                                    <div className="flex flex-wrap gap-1">
                                                                        {perms.map(p => (
                                                                            <Badge key={p.id} variant="outline" className="text-[9px] bg-card/50 px-1.5 py-0" title={p.name}>
                                                                                {p.friendly_name}
                                                                            </Badge>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            });
                        })()}
                    </div>
                    <DialogFooter className="pt-3 sm:pt-4">
                        <Button variant="outline" className="w-full sm:w-auto rounded-xl gap-2" onClick={() => { if (viewingRole) openEditRole(viewingRole); setViewingRole(null); }}>
                            <Pencil className="h-4 w-4" /> Editar Rol
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function route(name: string, params?: any) {
    if (name === 'usuarios-roles.index') return '/usuarios-roles';
    if (name === 'usuarios-roles.store') return '/usuarios-roles';
    if (name === 'usuarios-roles.destroy') return `/usuarios-roles/${params}`;
    if (name === 'usuarios-roles.role.store') return '/usuarios-roles/role';
    if (name === 'usuarios-roles.role.update') return `/usuarios-roles/role/${params}`;
    if (name === 'usuarios-roles.role.destroy') return `/usuarios-roles/role/${params}`;
    if (name === 'usuarios-roles.permission.store') return '/usuarios-roles/permission';
    if (name === 'usuarios-roles.permission.update') return `/usuarios-roles/permission/${params}`;
    if (name === 'usuarios-roles.permission.destroy') return `/usuarios-roles/permission/${params}`;
    if (name === 'usuarios-roles.toggle-official') return `/usuarios-roles/public-profile/${params}/toggle-official`;
    if (name === 'usuarios-roles.user.store') return '/usuarios-roles/user/create';
    if (name === 'usuarios-roles.user.update') return `/usuarios-roles/user/${params}`;
    if (name === 'usuarios-roles.user.reset-password') return `/usuarios-roles/user/${params}/reset-password`;
    if (name === 'usuarios-roles.user.toggle-ban') return `/usuarios-roles/user/${params}/toggle-ban`;
    return '#';
}
