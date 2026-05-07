export type User = {
    id: number;
    name: string;
    email: string;
    agency_id: number | null;
    avatar?: string | null;
    must_change_password: boolean;
    two_factor_enabled: boolean;
};

export type Auth = {
    user: User;
    agencyId: number | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
