export type User = {
    id: number;
    name: string;
    email: string;
    profile_photo_url?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    roles?: string[];
    permissions?: string[];
    public_profile?: { slug: string; title: string; is_active: boolean } | null;
    unread_messages?: number;
    pending_orders?: number;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
