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
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface SidebarProject {
    id: number;
    name: string;
    color: string;
}

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
}

export interface Project {
    id: number;
    name: string;
    description: string | null;
    color: string;
    start_date: string | null;
    end_date: string | null;
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
    subtasks_count?: number;
    comments_count?: number;
}

export interface Comment {
    id: number;
    body: string;
    created_at: string;
    user: MiniUser;
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
    activities: Activity[];
}

export interface ProjectPageProps {
    project: Project;
    members: Member[];
    can: { edit: boolean; manage: boolean };
}

export interface RuleAction {
    type: string;
    section_id?: number;
    user_id?: number;
    priority?: Priority;
    days?: number;
    text?: string;
}

export interface Rule {
    id: number;
    name: string;
    enabled: boolean;
    trigger: string;
    trigger_section_id: number | null;
    trigger_section: { id: number; name: string } | null;
    actions: RuleAction[];
}
