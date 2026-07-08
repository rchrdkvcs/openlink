export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_instance_admin?: boolean;
    profile_avatar_url?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
