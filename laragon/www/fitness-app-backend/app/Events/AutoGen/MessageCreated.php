<?php

namespace App\Events\AutoGen;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\AutoGenMessage;

/**
 * AutoGen消息创建事件
 */
class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $sessionId;

    /**
     * 创建新的事件实例
     */
    public function __construct(AutoGenMessage $message)
    {
        $this->message = $message;
        $this->sessionId = $message->session_id;
    }

    /**
     * 获取事件应该广播的频道
     */
    public function broadcastOn()
    {
        return new PrivateChannel('autogen.session.' . $this->sessionId);
    }

    /**
     * 广播事件名称
     */
    public function broadcastAs()
    {
        return 'message.created';
    }

    /**
     * 广播数据
     */
    public function broadcastWith()
    {
        return [
            'message_id' => $this->message->id,
            'session_id' => $this->message->session_id,
            'sender_type' => $this->message->sender_type,
            'sender_id' => $this->message->sender_id,
            'content' => $this->message->content,
            'message_type' => $this->message->message_type,
            'processing_status' => $this->message->processing_status,
            'metadata' => $this->message->metadata,
            'created_at' => $this->message->created_at->toISOString(),
            'sender_name' => $this->getSenderName()
        ];
    }

    /**
     * 获取发送者名称
     */
    protected function getSenderName(): string
    {
        switch ($this->message->sender_type) {
            case 'user':
                return $this->message->user ? $this->message->user->name : '用户';
            case 'agent':
                return $this->message->agent ? $this->message->agent->agent_name : 'AI助手';
            case 'system':
                return '系统';
            default:
                return '未知发送者';
        }
    }
}