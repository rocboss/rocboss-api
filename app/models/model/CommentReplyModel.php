<?php
namespace model;

/**
 * CommentReplyModel
 * @author ROC <i@rocs.me>
 */
class CommentReplyModel extends Model
{
    // The table name.
    const TABLE = 'rocboss_comment_reply';
    
    // Columns the model expects to exist
    const COLUMNS = ['id', 'comment_id', 'user_id', 'at_user_id', 'content', 'created_at', 'updated_at', 'is_deleted'];

    // List of columns which have a default value or are nullable
    const OPTIONAL_COLUMNS = ['created_at'];

    // Primary Key
    const PRIMARY_KEY = ['id'];

    // List of columns to receive the current timestamp automatically
    const STAMP_COLUMNS = [
        'updated_at' => 'datetime',
    ];

    // It defines the column affected by the soft delete
    const SOFT_DELETE = 'is_deleted';
}
