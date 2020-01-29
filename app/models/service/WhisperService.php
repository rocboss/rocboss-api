<?php
namespace service;

/**
 * WhisperService
 * @author ROC <i@rocs.me>
 */
class WhisperService extends Service
{
    const WHISPER_TIME = 600;
    const WHISPER_FREQUENCY_UPPER_LIMIT = 10;

    /**
     * 校验用户是否能发私信
     *
     * @param integer $senderUserId
     * @param integer $receiveUserId
     * @return boolean
     */
    public function checkWhisperAuth($senderUserId, $receiveUserId)
    {
        if ($senderUserId == $receiveUserId) {
            $this->_error = '不能给自己发私信';
            return false;
        }

        // 用户is_banned
        $this->userModel->load([
            'id' => $senderUserId,
            'is_banned' => 1,
            'is_deleted' => 0
        ]);

        if (!empty($this->userModel->getData())) {
            $this->_error = '你已被封禁，无法发送私信';
            return false;
        }

        $this->userModel->load([
            'id' => $receiveUserId,
            'is_banned' => 1,
            'is_deleted' => 0
        ]);

        if (!empty($this->userModel->getData())) {
            $this->_error = '该用户已被封禁，无法接收私信';
            return false;
        }

        // 检测用户一段时间内是否已达频次上限
        $currentTime = time();
        $count = $this->userWhisperModel->count([
            'sender_user_id' => $senderUserId,
            'receiver_user_id' => $receiveUserId,
            "create_time[>=]" => $currentTime - self::WHISPER_TIME
        ]);
        if ($count > self::WHISPER_FREQUENCY_UPPER_LIMIT) {
            $this->_error = '已达私信数量上限，请稍后再试';
            return false;
        }

        return true;
    }

    /**
     * 用户私信
     *
     * @param integer $senderUserId
     * @param integer $receiveUserId
     * @return boolean
     */
    public function add($senderUserId, $receiveUserId, $content)
    {
        $model = $this->userWhisperModel;
        $model->sender_user_id = $senderUserId;
        $model->receiver_user_id = $receiveUserId;
        $model->content = $content;
        if ($model->save()) {
            // 获取私信id
            $whisperId = $model->getPrimaryKey()['id'];
            // 添加message
            $messageService = new \service\MessageService();
            $messageService->addWhisperMessage($senderUserId, $receiveUserId, $whisperId);
            
            return true;
        }
        $this->_error = '发送私信失败';
        return false;
    }
}
