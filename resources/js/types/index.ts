import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    sidebarProjects: SidebarProject[];
    unreadNotifications: number;
    flash?: { error?: string | null; success?: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    color: string;
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export type SidebarProject = ProjectBadge;

export interface MiniUser {
    id: number;
    name: string;
    color: string;
    avatar?: string | null;
}

export interface Member extends MiniUser {
    email: string;
    role: 'admin' | 'editor' | 'viewer';
    pending?: boolean;
    deactivated?: boolean;
}

/** Avatar d'un projet : image envoyée, sinon emoji, sinon pastille de couleur avec l'initiale. */
export interface ProjectBadge {
    id: number;
    name: string;
    color: string;
    icon?: string | null;
    image?: string | null;
    /** En cours, non commencé (date de début à venir) ou terminé. */
    status?: ProjectStatus;
}

export type ProjectStatus = 'active' | 'upcoming' | 'done';

export interface Project extends ProjectBadge {
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    completed_at?: string | null;
}

export interface Section {
    id: number;
    name: string;
    position: number;
}

export type Priority = 'none' | 'low' | 'medium' | 'high';

export interface Task {
    id: number;
    project_id: number;
    section_id: number;
    parent_id: number | null;
    assignee_id: number | null;
    title: string;
    description: string | null;
    start_date: string | null;
    due_date: string | null;
    priority: Priority;
    completed: boolean;
    position: number;
    assignee?: MiniUser | null;
    completed_at?: string | null;
    created_at?: string;
    updated_at?: string;
    subtasks_count?: number;
    subtasks_done_count?: number;
    comments_count?: number;
    attachments_count?: number;
    /** Pièce jointe affichée en couverture de la carte (dernière image, sinon dernier fichier). */
    cover?: TaskCover | null;
}

export type TaskCover = Pick<Attachment, 'id' | 'name' | 'kind' | 'extension'>;

export interface Comment {
    id: number;
    body: string;
    created_at: string;
    user: MiniUser;
}

export type AttachmentKind = 'image' | 'video' | 'audio' | 'pdf' | 'text' | 'other';

export interface Attachment {
    id: number;
    user_id: number | null;
    name: string;
    mime: string | null;
    size: number;
    kind: AttachmentKind;
    extension: string;
    created_at: string;
    user: { id: number; name: string } | null;
}

export interface Activity {
    id: number;
    message: string;
    created_at: string;
    user: { id: number; name: string } | null;
}

export interface TaskDetail extends Task {
    parent: { id: number; title: string } | null;
    subtasks: Task[];
    comments: Comment[];
    attachments: Attachment[];
    activities: Activity[];
}

export interface ProjectPageProps {
    project: Project;
    members: Member[];
    can: { edit: boolean; add: boolean; manage: boolean };
}

export interface RuleAction {
    type: string;
    section_id?: number;
    section_name?: string;
    user_id?: number;
    priority?: Priority;
    days?: number;
    text?: string;
}

export interface Rule {
    id: number;
    project_id: number | null;
    name: string;
    enabled: boolean;
    trigger: string;
    trigger_section_id: number | null;
    trigger_section_name: string | null;
    trigger_section?: { id: number; name: string } | null;
    project?: ProjectBadge | null;
    actions: RuleAction[];
}
