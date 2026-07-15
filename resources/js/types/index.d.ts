export interface User {
    id: number;
    first_name: string;
    last_name: string;
    name: string; // Computed from first_name + last_name
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    households: Array<{ id: number; name: string }>;
};
