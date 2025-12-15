<?php

declare(strict_types=1);

namespace App\Modules\Notes\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class WikiLinkService
{
    // Pattern to match [[link]] or [[link|display text]]
    private const WIKI_LINK_PATTERN = '/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/';

    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Extract wiki links from content
     * Returns array of ['target' => 'slug-or-title', 'text' => 'display text']
     */
    public function extractLinks(string $content): array
    {
        $links = [];

        if (preg_match_all(self::WIKI_LINK_PATTERN, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $target = trim($match[1]);
                $text = isset($match[2]) ? trim($match[2]) : $target;

                if (!empty($target)) {
                    $links[] = [
                        'target' => $target,
                        'text' => $text,
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * Update wiki links for a note
     * Parses content, resolves targets, and stores in note_links table
     */
    public function updateLinks(string $sourceNoteId, string $userId, string $content): void
    {
        // Remove existing links from this source
        $this->db->delete('note_links', ['source_note_id' => $sourceNoteId]);

        // Extract links from content
        $links = $this->extractLinks($content);

        foreach ($links as $link) {
            // Try to resolve the target note
            $targetNote = $this->resolveTarget($userId, $link['target']);

            if ($targetNote && $targetNote['id'] !== $sourceNoteId) {
                try {
                    $this->db->insert('note_links', [
                        'id' => Uuid::uuid4()->toString(),
                        'source_note_id' => $sourceNoteId,
                        'target_note_id' => $targetNote['id'],
                        'link_text' => $link['text'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Exception $e) {
                    // Ignore duplicate links
                }
            }
        }
    }

    /**
     * Resolve a wiki link target to a note
     * Tries: exact slug match, then title match (case-insensitive)
     */
    public function resolveTarget(string $userId, string $target): ?array
    {
        // Normalize target for slug matching
        $slug = $this->slugify($target);

        // Try exact slug match first
        $note = $this->db->fetchAssociative(
            'SELECT id, title, slug FROM notes WHERE user_id = ? AND slug = ? AND is_deleted = FALSE',
            [$userId, $slug]
        );

        if ($note) {
            return $note;
        }

        // Try title match (case-insensitive)
        $note = $this->db->fetchAssociative(
            'SELECT id, title, slug FROM notes WHERE user_id = ? AND LOWER(title) = LOWER(?) AND is_deleted = FALSE',
            [$userId, $target]
        );

        return $note ?: null;
    }

    /**
     * Get all notes that link to the given note (backlinks)
     */
    public function getBacklinks(string $noteId, string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT n.id, n.title, n.slug, n.icon, nl.link_text,
                    SUBSTRING(n.content, 1, 200) as preview
             FROM note_links nl
             JOIN notes n ON nl.source_note_id = n.id
             WHERE nl.target_note_id = ? AND n.user_id = ? AND n.is_deleted = FALSE
             ORDER BY n.updated_at DESC",
            [$noteId, $userId]
        );
    }

    /**
     * Get all outgoing links from a note
     */
    public function getOutgoingLinks(string $noteId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT n.id, n.title, n.slug, n.icon, nl.link_text
             FROM note_links nl
             JOIN notes n ON nl.target_note_id = n.id
             WHERE nl.source_note_id = ? AND n.is_deleted = FALSE
             ORDER BY n.title ASC",
            [$noteId]
        );
    }

    /**
     * Check if a link exists between two notes
     */
    public function linkExists(string $sourceNoteId, string $targetNoteId): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM note_links WHERE source_note_id = ? AND target_note_id = ?',
            [$sourceNoteId, $targetNoteId]
        );
    }

    /**
     * Get unlinked mentions (notes that mention this note's title but don't have a formal link)
     */
    public function getUnlinkedMentions(string $noteId, string $userId): array
    {
        // Get the note's title
        $note = $this->db->fetchAssociative(
            'SELECT title, slug FROM notes WHERE id = ?',
            [$noteId]
        );

        if (!$note) {
            return [];
        }

        $title = $note['title'];

        // Find notes that contain the title but don't have a link
        return $this->db->fetchAllAssociative(
            "SELECT n.id, n.title, n.slug, n.icon,
                    SUBSTRING(n.content, 1, 200) as preview
             FROM notes n
             WHERE n.user_id = ?
               AND n.id != ?
               AND n.is_deleted = FALSE
               AND (n.content LIKE ? OR n.content LIKE ?)
               AND n.id NOT IN (
                   SELECT source_note_id FROM note_links WHERE target_note_id = ?
               )
             ORDER BY n.updated_at DESC
             LIMIT 20",
            [$userId, $noteId, '%' . $title . '%', '%[[' . $title . '%', $noteId]
        );
    }

    /**
     * Convert wiki links in content to HTML links
     */
    public function renderLinks(string $content, string $userId, string $baseUrl = '/notes/'): string
    {
        return preg_replace_callback(
            self::WIKI_LINK_PATTERN,
            function ($match) use ($userId, $baseUrl) {
                $target = trim($match[1]);
                $text = isset($match[2]) ? trim($match[2]) : $target;

                $note = $this->resolveTarget($userId, $target);

                if ($note) {
                    return sprintf(
                        '<a href="%s%s" class="wiki-link" data-note-id="%s">%s</a>',
                        $baseUrl,
                        htmlspecialchars($note['slug']),
                        htmlspecialchars($note['id']),
                        htmlspecialchars($text)
                    );
                } else {
                    // Broken link - note doesn't exist
                    return sprintf(
                        '<a href="#" class="wiki-link wiki-link-broken" data-target="%s">%s</a>',
                        htmlspecialchars($target),
                        htmlspecialchars($text)
                    );
                }
            },
            $content
        );
    }

    /**
     * Simple slugify for target matching
     */
    private function slugify(string $text): string
    {
        $replacements = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text;
    }

    /**
     * Get link graph data for visualization
     */
    public function getLinkGraph(string $userId, int $limit = 100): array
    {
        // Get nodes (notes)
        $nodes = $this->db->fetchAllAssociative(
            "SELECT id, title, slug, icon,
                    (SELECT COUNT(*) FROM note_links WHERE target_note_id = n.id) as backlink_count
             FROM notes n
             WHERE user_id = ? AND is_deleted = FALSE AND is_template = FALSE
             ORDER BY backlink_count DESC, updated_at DESC
             LIMIT ?",
            [$userId, $limit],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        $nodeIds = array_column($nodes, 'id');

        if (empty($nodeIds)) {
            return ['nodes' => [], 'edges' => []];
        }

        // Get edges (links between these nodes)
        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $edges = $this->db->fetchAllAssociative(
            "SELECT source_note_id as source, target_note_id as target
             FROM note_links
             WHERE source_note_id IN ({$placeholders}) AND target_note_id IN ({$placeholders})",
            array_merge($nodeIds, $nodeIds)
        );

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
