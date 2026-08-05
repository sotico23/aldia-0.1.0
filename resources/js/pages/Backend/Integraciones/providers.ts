import {
    Coins,
    CreditCard,
    Facebook,
    MessageCircle,
    Send,
    ShoppingBag,
    ShoppingCart,
    Store,
    Wallet,
    Workflow,
    type LucideIcon,
} from 'lucide-react';

export type ProviderCategory = 'payment' | 'bots' | 'ecommerce';

export interface ProviderField {
    key: string;
    label: string;
    placeholder: string;
    type?: 'text' | 'password' | 'url' | 'number';
}

export interface ProviderEnvironment {
    value: string;
    label: string;
}

export interface ProviderDefinition {
    id: string;
    name: string;
    description: string;
    icon: LucideIcon;
    category: ProviderCategory;
    fields: ProviderField[];
    environments?: ProviderEnvironment[];
}

export const PROVIDERS: ProviderDefinition[] = [
    {
        id: 'webpay',
        name: 'Webpay (Transbank)',
        description: 'Pasarela de pagos con tarjetas de débito y crédito de Chile.',
        icon: CreditCard,
        category: 'payment',
        fields: [
            { key: 'commerce_code', label: 'Commerce Code', placeholder: '5970...', type: 'number' },
            { key: 'api_key', label: 'API Key', placeholder: '••••••••••••••••', type: 'password' },
        ],
        environments: [
            { value: 'integration', label: 'Integración' },
            { value: 'production', label: 'Producción' },
        ],
    },
    {
        id: 'mercadopago',
        name: 'Mercado Pago',
        description: 'Pasarela de pagos de Mercado Libre para LATAM.',
        icon: Wallet,
        category: 'payment',
        fields: [
            { key: 'mercadopago_public_key', label: 'Public Key', placeholder: 'APP_USR-...' },
            { key: 'mercadopago_access_token', label: 'Access Token', placeholder: '••••••••••••••••', type: 'password' },
        ],
        environments: [
            { value: 'sandbox', label: 'Sandbox' },
            { value: 'production', label: 'Producción' },
        ],
    },
    {
        id: 'paypal',
        name: 'PayPal',
        description: 'Pasarela de pagos internacional con PayPal Checkout.',
        icon: Coins,
        category: 'payment',
        fields: [
            { key: 'paypal_client_id', label: 'Client ID', placeholder: 'A...' },
            { key: 'paypal_client_secret', label: 'Client Secret', placeholder: '••••••••••••••••', type: 'password' },
        ],
        environments: [
            { value: 'sandbox', label: 'Sandbox' },
            { value: 'live', label: 'Producción' },
        ],
    },
    {
        id: 'n8n',
        name: 'n8n',
        description: 'Automatizaciones de flujos de trabajo (proxy Telegram y webhooks).',
        icon: Workflow,
        category: 'bots',
        fields: [
            { key: 'telegram_proxy_url', label: 'Telegram Proxy URL', placeholder: 'https://n8n.tudominio.com/webhook/...', type: 'url' },
            { key: 'api_key', label: 'API Key', placeholder: '••••••••••••••••', type: 'password' },
        ],
    },
    {
        id: 'telegram',
        name: 'Telegram Bot',
        description: 'Bot oficial de Telegram para atención y notificaciones.',
        icon: Send,
        category: 'bots',
        fields: [
            { key: 'telegram_bot_token', label: 'Bot Token', placeholder: '123456:ABC-DEF...', type: 'password' },
            { key: 'telegram_bot_username', label: 'Username del Bot', placeholder: '@mibot' },
        ],
    },
    {
        id: 'whatsapp',
        name: 'WhatsApp Business',
        description: 'API oficial de WhatsApp Business (Meta Graph API).',
        icon: MessageCircle,
        category: 'bots',
        fields: [
            { key: 'whatsapp_phone_number_id', label: 'Phone Number ID', placeholder: '123456789012345' },
            { key: 'whatsapp_access_token', label: 'Access Token', placeholder: '••••••••••••••••', type: 'password' },
            { key: 'whatsapp_business_id', label: 'Business ID', placeholder: '123456789012345' },
            { key: 'whatsapp_api_version', label: 'API Version', placeholder: 'v22.0' },
        ],
    },
    {
        id: 'facebook_meta',
        name: 'Facebook / Meta',
        description: 'Anuncios y audiencias de Meta for Business.',
        icon: Facebook,
        category: 'bots',
        fields: [
            { key: 'access_token', label: 'Access Token', placeholder: '••••••••••••••••', type: 'password' },
        ],
    },
    {
        id: 'shopify',
        name: 'Shopify',
        description: 'Sincroniza tu tienda online de Shopify.',
        icon: ShoppingBag,
        category: 'ecommerce',
        fields: [
            { key: 'shop_domain', label: 'Dominio de la tienda', placeholder: 'mitienda' },
            { key: 'access_token', label: 'Access Token', placeholder: 'shpat_...', type: 'password' },
            { key: 'api_version', label: 'API Version', placeholder: '2024-10' },
        ],
    },
    {
        id: 'woocommerce',
        name: 'WooCommerce',
        description: 'Conecta tu tienda WooCommerce (WordPress + WooCommerce).',
        icon: Store,
        category: 'ecommerce',
        fields: [
            { key: 'store_url', label: 'URL de la tienda', placeholder: 'https://mitienda.com', type: 'url' },
            { key: 'consumer_key', label: 'Consumer Key', placeholder: 'ck_...' },
            { key: 'consumer_secret', label: 'Consumer Secret', placeholder: '••••••••••••••••', type: 'password' },
        ],
    },
    {
        id: 'mercado_libre',
        name: 'Mercado Libre',
        description: 'Sincroniza publicaciones y ventas de Mercado Libre.',
        icon: ShoppingCart,
        category: 'ecommerce',
        fields: [
            { key: 'access_token', label: 'Access Token', placeholder: '••••••••••••••••', type: 'password' },
        ],
    },
];

export const MASKED = '••••••••••••••••';

export function isMasked(value: string | null | undefined): boolean {
    return value === MASKED;
}
