<?php

declare(strict_types=1);

namespace App\Modules\Notes\Services;

use Doctrine\DBAL\Connection;

class NoteService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Generate a unique slug for a note
     */
    public function generateUniqueSlug(string $userId, string $title, ?string $excludeNoteId = null): string
    {
        // Convert title to slug
        $slug = $this->slugify($title);

        if (empty($slug)) {
            $slug = 'untitled';
        }

        // Check for uniqueness
        $baseSlug = $slug;
        $counter = 1;

        while (true) {
            $query = 'SELECT id FROM notes WHERE user_id = ? AND slug = ? AND is_deleted = FALSE';
            $params = [$userId, $slug];

            if ($excludeNoteId) {
                $query .= ' AND id != ?';
                $params[] = $excludeNoteId;
            }

            $existing = $this->db->fetchOne($query, $params);

            if (!$existing) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Convert string to URL-friendly slug
     */
    public function slugify(string $text): string
    {
        // Replace umlauts and special chars
        $replacements = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace non-alphanumeric with dashes
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing dashes
        $text = trim($text, '-');

        // Limit length
        if (strlen($text) > 100) {
            $text = substr($text, 0, 100);
            $text = rtrim($text, '-');
        }

        return $text;
    }

    /**
     * Count words in content (strips HTML)
     */
    public function countWords(string $content): int
    {
        // Strip HTML tags
        $text = strip_tags($content);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace multiple whitespace with single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        $text = trim($text);

        if (empty($text)) {
            return 0;
        }

        // Count words
        return str_word_count($text, 0, 'äöüÄÖÜßéèêëáàâãíìîïóòôõúùûñç0123456789');
    }

    /**
     * Build hierarchical tree from flat array
     */
    public function buildTree(array $notes, ?string $parentId = null): array
    {
        $tree = [];

        foreach ($notes as $note) {
            if ($note['parent_id'] === $parentId) {
                $children = $this->buildTree($notes, $note['id']);
                $note['children'] = $children;
                $note['is_pinned'] = (bool) $note['is_pinned'];
                $note['is_archived'] = (bool) $note['is_archived'];
                $note['is_template'] = (bool) $note['is_template'];
                $note['children_count'] = (int) $note['children_count'];
                $tree[] = $note;
            }
        }

        return $tree;
    }

    /**
     * Get breadcrumb path for a note
     */
    public function getBreadcrumb(string $noteId): array
    {
        $breadcrumb = [];
        $currentId = $noteId;
        $maxDepth = 20; // Prevent infinite loops
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {
            $note = $this->db->fetchAssociative(
                'SELECT id, parent_id, title, slug, icon FROM notes WHERE id = ?',
                [$currentId]
            );

            if (!$note) {
                break;
            }

            array_unshift($breadcrumb, [
                'id' => $note['id'],
                'title' => $note['title'],
                'slug' => $note['slug'],
                'icon' => $note['icon'],
            ]);

            $currentId = $note['parent_id'];
            $depth++;
        }

        return $breadcrumb;
    }

    /**
     * Check if a note is a descendant of another
     */
    public function isDescendant(string $potentialDescendantId, string $ancestorId): bool
    {
        $currentId = $potentialDescendantId;
        $maxDepth = 50;
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {
            if ($currentId === $ancestorId) {
                return true;
            }

            $parentId = $this->db->fetchOne(
                'SELECT parent_id FROM notes WHERE id = ?',
                [$currentId]
            );

            $currentId = $parentId ?: null;
            $depth++;
        }

        return false;
    }

    /**
     * Get all descendant IDs of a note
     */
    public function getDescendantIds(string $noteId): array
    {
        $descendants = [];
        $this->collectDescendants($noteId, $descendants);
        return $descendants;
    }

    private function collectDescendants(string $parentId, array &$collected): void
    {
        $children = $this->db->fetchAllAssociative(
            'SELECT id FROM notes WHERE parent_id = ? AND is_deleted = FALSE',
            [$parentId]
        );

        foreach ($children as $child) {
            $collected[] = $child['id'];
            $this->collectDescendants($child['id'], $collected);
        }
    }

    /**
     * Auto-clean old trash items (called by scheduler)
     */
    public function cleanOldTrash(int $daysOld = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $oldNotes = $this->db->fetchAllAssociative(
            'SELECT id FROM notes WHERE is_deleted = TRUE AND deleted_at < ?',
            [$cutoffDate]
        );

        foreach ($oldNotes as $note) {
            $this->db->delete('note_links', ['source_note_id' => $note['id']]);
            $this->db->delete('note_links', ['target_note_id' => $note['id']]);
            $this->db->delete('note_favorites', ['note_id' => $note['id']]);
            $this->db->delete('note_recent', ['note_id' => $note['id']]);
            $this->db->delete('note_tags', ['note_id' => $note['id']]);
            $this->db->delete('note_versions', ['note_id' => $note['id']]);
            $this->db->delete('notes', ['id' => $note['id']]);
        }

        return count($oldNotes);
    }
}
