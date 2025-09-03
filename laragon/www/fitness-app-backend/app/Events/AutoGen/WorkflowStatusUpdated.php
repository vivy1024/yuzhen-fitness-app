<?php

namespace App\Events\AutoGen;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\AutoGenWorkflow;

/**
 * AutoGen工作流状态更新事件
 */
class WorkflowStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $workflow;
    public $oldStatus;
    public $sessionId;

    /**
     * 创建新的事件实例
     */
    public function __construct(AutoGenWorkflow $workflow, string $oldStatus)
    {
        $this->workflow = $workflow;
        $this->oldStatus = $oldStatus;
        $this->sessionId = $workflow->session_id;
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
        return 'workflow.status_updated';
    }

    /**
     * 广播数据
     */
    public function broadcastWith()
    {
        return [
            'workflow_id' => $this->workflow->id,
            'session_id' => $this->workflow->session_id,
            'workflow_name' => $this->workflow->workflow_name,
            'workflow_type' => $this->workflow->workflow_type,
            'old_status' => $this->oldStatus,
            'new_status' => $this->workflow->status,
            'priority' => $this->workflow->priority,
            'current_step' => $this->workflow->current_step,
            'total_steps' => $this->getTotalSteps(),
            'progress_percentage' => $this->calculateProgress(),
            'quality_score' => $this->workflow->quality_score,
            'updated_at' => $this->workflow->updated_at->toISOString()
        ];
    }

    /**
     * 获取总步数
     */
    protected function getTotalSteps(): int
    {
        if (!isset($this->workflow->definition['steps']) || !is_array($this->workflow->definition['steps'])) {
            return 0;
        }
        
        return count($this->workflow->definition['steps']);
    }

    /**
     * 计算进度百分比
     */
    protected function calculateProgress(): float
    {
        $totalSteps = $this->getTotalSteps();
        
        if ($totalSteps === 0) {
            return 0.0;
        }
        
        $currentStep = $this->workflow->current_step;
        
        if ($currentStep <= 0) {
            return 0.0;
        }
        
        if ($currentStep >= $totalSteps) {
            return 100.0;
        }
        
        return round(($currentStep / $totalSteps) * 100, 2);
    }
}