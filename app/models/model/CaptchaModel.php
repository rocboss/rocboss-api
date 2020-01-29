<?php
namespace model;

/**
 * CaptchaModel
 * @author ROC <i@rocs.me>
 */
class CaptchaModel extends Model
{
    // The table name.
    const TABLE = 'rocboss_captcha';
    
    // Columns the model expects to exist
    const COLUMNS = ['id', 'email', 'captcha', 'use_times', 'expired_at', 'created_at', 'updated_at', 'is_deleted'];

    // List of columns which have a default value or are nullable
    const OPTIONAL_COLUMNS = ['use_times', 'created_at'];

    // Primary Key
    const PRIMARY_KEY = ['id'];

    // List of columns to receive the current timestamp automatically
    const STAMP_COLUMNS = [
        'updated_at' => 'datetime',
    ];

    // It defines the column affected by the soft delete
    const SOFT_DELETE = 'is_deleted';

    // 验证码超时分钟
    const EXPIRE_MINUTE = 10;
}
