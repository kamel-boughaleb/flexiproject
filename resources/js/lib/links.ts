/**
 * Ouvre dans un nouvel onglet le lien cliqué dans un texte riche (description, commentaire).
 * Renvoie true si le clic a été pris en charge. Les liens internes « # » et les mentions sont ignorés.
 */
export function openRichLink(event: MouseEvent): boolean {
    if (event.button !== 0) return false;
    const anchor = (event.target as HTMLElement | null)?.closest?.('a[href]') as HTMLAnchorElement | null;
    const href = anchor?.getAttribute('href');
    if (!anchor || !href || href.startsWith('#') || !/^(https?:|mailto:|tel:|\/)/i.test(href)) return false;
    event.preventDefault();
    window.open(anchor.href, '_blank', 'noopener,noreferrer');
    return true;
}
