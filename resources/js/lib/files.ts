import type { AttachmentKind } from '@/types';
import { FileCode, FileIcon, FileText, Film, Image, Music } from 'lucide-vue-next';

/** Vignette de chaque type de fichier : fond et couleur de la marque. */
export const FILE_TILES: Record<AttachmentKind, string> = {
    image: 'bg-info-tint text-info-active',
    video: 'bg-info-tint text-info-active',
    audio: 'bg-warning-tint text-warning-text',
    pdf: 'bg-brand-tint text-brand-active',
    text: 'bg-success-tint text-success-text',
    other: 'bg-app-hover text-ink-soft',
};

export const FILE_ICONS: Record<AttachmentKind, typeof FileIcon> = {
    image: Image,
    video: Film,
    audio: Music,
    pdf: FileText,
    text: FileCode,
    other: FileIcon,
};

/** Taille lisible : 1,2 Mo, 340 Ko… */
export function fileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} o`;
    const units = ['Ko', 'Mo', 'Go'];
    let value = bytes / 1024;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }
    return `${value.toLocaleString('fr-FR', { maximumFractionDigits: value < 10 ? 1 : 0 })} ${units[i]}`;
}

/** Langage highlight.js correspondant à une extension de fichier. */
const LANGUAGES: Record<string, string> = {
    js: 'javascript',
    mjs: 'javascript',
    cjs: 'javascript',
    jsx: 'javascript',
    ts: 'typescript',
    tsx: 'typescript',
    vue: 'xml',
    html: 'xml',
    htm: 'xml',
    svg: 'xml',
    xml: 'xml',
    yml: 'yaml',
    env: 'ini',
    conf: 'ini',
    sh: 'bash',
    md: 'markdown',
    py: 'python',
    rb: 'ruby',
    h: 'c',
    cs: 'csharp',
    rs: 'rust',
    kt: 'kotlin',
    txt: 'plaintext',
    log: 'plaintext',
    csv: 'plaintext',
    tsv: 'plaintext',
};

export function languageOf(extension: string): string {
    return LANGUAGES[extension] ?? extension;
}
