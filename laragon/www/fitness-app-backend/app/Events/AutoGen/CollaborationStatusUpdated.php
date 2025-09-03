<?php

namespace App\Events\AutoGen;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\AutoGenCollaboration;

/**
 * AutoGen协作状态更新事件
 */
class CollaborationStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $collaboration;
    public $oldStatus;
    public $sessionId;

    /**
     * 创建新的事件实例
     */
    public function __construct(AutoGenCollaboration $collaboration, string $oldStatus)
    {
        $this->collaboration = $collaboration;
        $this->oldStatus = $oldStatus;
        $this->sessionId = $collaboration->session_id;
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
        return 'collaboration.status_updated';
    }

    /**
     * 广播数据
     */
    public function broadcastWith()
    {
        return [
            'collaboration_id' => $this->collaboration->id,
            'session_id' => $this->collaboration->session_id,
            'collaboration_type' => $this->collaboration->collaboration_type,
            'old_status' => $this->oldStatus,
            'new_status' => $this->collaboration->status,
            'priority' => $this->collaboration->priority,
            'current_round' => $this->collaboration->current_round,
            'confidence_score' => $this->collaboration->confidence_score,
            'participating_agents' => $this->collaboration->participating_agents,
            'updated_at' => $this->collaboration->updated_at->toISOString()
        ];
    }
}
