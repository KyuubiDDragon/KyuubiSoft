-- Kanban Card Activity Log
-- Tracks all changes and actions on kanban cards

CREATE TABLE IF NOT EXISTS kanban_card_activities (
    id CHAR(36) NOT NULL PRIMARY KEY,
    card_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (card_id) REFERENCES kanban_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_card_activities (card_id, created_at DESC),
    INDEX idx_user_activities (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Action types:
-- card_created: Card was created
-- card_moved: Card moved to different column (details: {from_column, to_column})
-- card_updated: Card title/description updated (details: {field, old_value, new_value})
-- assignee_added: User assigned to card (details: {assignee_id, assignee_name})
-- assignee_removed: User unassigned from card (details: {assignee_id, assignee_name})
-- flag_changed: Priority/flag changed (details: {old_flag, new_flag})
-- tag_added: Tag added to card (details: {tag_id, tag_name})
-- tag_removed: Tag removed from card (details: {tag_id, tag_name})
-- checklist_added: Checklist created (details: {checklist_id, title})
-- checklist_completed: All items in checklist completed (details: {checklist_id, title})
-- checklist_item_completed: Single item completed (details: {item_id, item_text})
-- checklist_item_uncompleted: Single item uncompleted (details: {item_id, item_text})
-- comment_added: Comment added (details: {comment_id})
-- attachment_added: Attachment added (details: {attachment_id, filename})
-- attachment_removed: Attachment removed (details: {filename})
-- link_added: Link to document/list/etc added (details: {link_type, link_id, link_title})
-- link_removed: Link removed (details: {link_type, link_id, link_title})
-- due_date_set: Due date set (details: {due_date})
-- due_date_removed: Due date removed (details: {old_due_date})
