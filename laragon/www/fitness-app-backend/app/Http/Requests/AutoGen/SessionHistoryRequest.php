<?php

namespace App\Http\Requests\AutoGen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Constants\ErrorCodes;

/**
 * AutoGen会话历史请求验证类
 * 
 * 验证获取会话历史请求的参数
 * 
 * @author Backend Optimization Team
 * @version 1.0.0
 * @created 2025-01-27
 * @updated 2025-01-27
 */
class SessionHistoryRequest extends FormRequest
{
    /**
     * 确定用户是否有权限进行此请求
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 获取应用于请求的验证规则
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/'
            ],
            'session_id' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/'
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100'
            ],
            'offset' => [
                'nullable',
                'integer',
                'min:0'
            ],
            'start_date' => [
                'nullable',
                'date',
                'before_or_equal:end_date'
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                'before_or_equal:today'
            ],
            'message_type' => [
                'nullable',
                'string',
                'in:user,assistant,system,all'
            ],
            'agent_type' => [
                'nullable',
                'string',
                'in:coordinator,fitness,nutrition,rehabilitation,all'
            ],
            'include_context' => [
                'nullable',
                'boolean'
            ],
            'include_metadata' => [
                'nullable',
                'boolean'
            ],
            'sort_order' => [
                'nullable',
                'string',
                'in:asc,desc'
            ],
            'search_query' => [
                'nullable',
                'string',
                'max:200',
                'min:2'
            ],
            'format' => [
                'nullable',
                'string',
                'in:full,summary,minimal'
            ]
        ];
    }

    /**
     * 获取验证错误的自定义消息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => '用户ID不能为空',
            'user_id.string' => '用户ID必须是字符串',
            'user_id.max' => '用户ID不能超过100个字符',
            'user_id.regex' => '用户ID格式不正确，只能包含字母、数字、下划线和连字符',
            
            'session_id.string' => '会话ID必须是字符串',
            'session_id.max' => '会话ID不能超过100个字符',
            'session_id.regex' => '会话ID格式不正确，只能包含字母、数字、下划线和连字符',
            
            'limit.integer' => '限制数量必须是整数',
            'limit.min' => '限制数量不能小于1',
            'limit.max' => '限制数量不能大于100',
            
            'offset.integer' => '偏移量必须是整数',
            'offset.min' => '偏移量不能小于0',
            
            'start_date.date' => '开始日期格式不正确',
            'start_date.before_or_equal' => '开始日期不能晚于结束日期',
            
            'end_date.date' => '结束日期格式不正确',
            'end_date.after_or_equal' => '结束日期不能早于开始日期',
            'end_date.before_or_equal' => '结束日期不能晚于今天',
            
            'message_type.in' => '消息类型只能是：user（用户）、assistant（助手）、system（系统）、all（全部）',
            
            'agent_type.in' => 'Agent类型只能是：coordinator（协调员）、fitness（健身）、nutrition（营养）、rehabilitation（康复）、all（全部）',
            
            'include_context.boolean' => '是否包含上下文必须是布尔值',
            'include_metadata.boolean' => '是否包含元数据必须是布尔值',
            
            'sort_order.in' => '排序方式只能是：asc（升序）、desc（降序）',
            
            'search_query.string' => '搜索查询必须是字符串',
            'search_query.max' => '搜索查询不能超过200个字符',
            'search_query.min' => '搜索查询至少需要2个字符',
            
            'format.in' => '格式只能是：full（完整）、summary（摘要）、minimal（最小）'
        ];
    }

    /**
     * 获取验证失败时的自定义属性名称
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => '用户ID',
            'session_id' => '会话ID',
            'limit' => '限制数量',
            'offset' => '偏移量',
            'start_date' => '开始日期',
            'end_date' => '结束日期',
            'message_type' => '消息类型',
            'agent_type' => 'Agent类型',
            'include_context' => '包含上下文',
            'include_metadata' => '包含元数据',
            'sort_order' => '排序方式',
            'search_query' => '搜索查询',
            'format' => '格式'
        ];
    }

    /**
     * 准备验证数据
     * 
     * 设置默认值
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'limit' => $this->limit ?? 20,
            'offset' => $this->offset ?? 0,
            'message_type' => $this->message_type ?? 'all',
            'agent_type' => $this->agent_type ?? 'all',
            'include_context' => $this->include_context ?? false,
            'include_metadata' => $this->include_metadata ?? false,
            'sort_order' => $this->sort_order ?? 'desc',
            'format' => $this->format ?? 'full'
        ]);
    }

    /**
     * 处理验证失败
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'code' => ErrorCodes::VALIDATION_ERROR,
                'message' => '请求参数验证失败',
                'errors' => $validator->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'request_id' => request()->header('X-Request-ID')
                ]
            ], 422)
        );
    }
}