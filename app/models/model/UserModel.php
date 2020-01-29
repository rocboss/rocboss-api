<?php
namespace model;

/**
 * UserModel
 * @author ROC <i@rocs.me>
 */
class UserModel extends Model
{
    // The table name.
    const TABLE = 'rocboss_user';
    
    // Columns the model expects to exist
    const COLUMNS = ['id', 'email', 'username', 'signature', 'avatar', 'password', 'claim_token', 'qq_openid', 'is_banned', 'role', 'balance', 'created_at', 'updated_at', 'is_deleted'];

    // List of columns which have a default value or are nullable
    const OPTIONAL_COLUMNS = ['signature', 'qq_openid', 'role', 'balance', 'created_at'];

    // Primary Key
    const PRIMARY_KEY = ['id'];

    // List of columns to receive the current timestamp automatically
    const STAMP_COLUMNS = [
        'updated_at' => 'datetime',
    ];

    // It defines the column affected by the soft delete
    const SOFT_DELETE = 'is_deleted';
}
