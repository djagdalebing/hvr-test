export interface Localization {
    id: number;
    name: string;
    lines?: {[key: string]: string};
    created_at?: string;
    updated_at?: string;
}
