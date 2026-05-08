export interface FileEntry {
    id: number;
    name: string;
    type?: string;
    mime?: string;
    file_name?: string;
    extension?: string;
    file_size?: number;
    url?: string;
    public_path?: string;
    parent_id?: number | null;
    user_id?: number;
    workspace_id?: number;
    created_at?: string;
    updated_at?: string;
}
