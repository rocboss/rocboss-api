<?php
namespace model;

/**
 * MessageModel
 * @author ROC <i@rocs.me>
 */
class MessageModel extends Model
{
    // The table name.
    const TABLE = 'rocboss_message';
    
    // Columns the model expects to exist
    const COLUMNS = ['id', 'type', 'sender_user_id', 'receiver_user_id', 'breif', 'content', 'is_read', 'post_id', 'comment_id', 'reply_id', 'group_id', 'whisper_id', 'created_at', 'updated_at', 'is_deleted'];

    // List of columns which have a default value or are nullable
    const OPTIONAL_COLUMNS = ['breif', 'content', 'post_id', 'comment_id', 'reply_id', 'group_id', 'whisper_id', 'created_at'];

    // Primary Key
    const PRIMARY_KEY = ['id'];

    // List of columns to receive the current timestamp automatically
    const STAMP_COLUMNS = [
        'updated_at' => 'datetime',
    ];

    // It defines the column affected by the soft delete
    const SOFT_DELETE = 'is_deleted';
}
