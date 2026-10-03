export function linkCountLabel(count: number): string {
  return `${count} link${count === 1 ? '' : 's'}`;
}

export function folderDeleteMessage(linksCount: number): string {
  return linksCount > 0
    ? `The ${linkCountLabel(linksCount)} inside will move to Unfiled. Nothing is deleted.`
    : 'This folder is empty.';
}

export function folderRename(currentName: string, input: string): string | null {
  const name = input.trim();
  return name && name !== currentName ? name : null;
}

export function needsMove(link: { folderId: number | null } | null, folderId: number | null): boolean {
  return link !== null && link.folderId !== folderId;
}
