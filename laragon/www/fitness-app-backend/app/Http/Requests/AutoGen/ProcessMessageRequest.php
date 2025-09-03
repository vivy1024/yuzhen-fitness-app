<?php

namespace App\Http\Requests\AutoGen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Constants\ErrorCodes;

/**
 * AutoGen消息处理请求验证类
 * 
 * 验证用户消息处理请求的参数
 * 
 * @author Backend Optimization Team
 * @version 1.0.0
 * @created 2025-01-27
 * @updated 2025-01-27
 */
class ProcessMessageRequest extends FormRequest
{
    /**
     * 确定用户是否有权限进行此请求
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // 暂时允许所有请求，后续可以添加认证逻辑
    }

    /**
     * 获取应用于请求的验证规则
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'max:2000',
                'min:1'
            ],
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
            'context' => [
                'nullable',
                'array'
            ],
            'context.user_profile' => [
                'nullable',
                'array'
            ],
            'context.user_profile.age' => [
                'nullable',
                'integer',
                'min:10',
                'max:100'
            ],
            'context.user_profile.gender' => [
                'nullable',
                'string',
                'in:男,女,其他'
            ],
            'context.user_profile.weight' => [
                'nullable',
                'numeric',
                'min:30',
                'max:300'
            ],
            'context.user_profile.height' => [
                'nullable',
                'numeric',
                'min:100',
                'max:250'
            ],
            'context.user_profile.fitness_level' => [
                'nullable',
                'string',
                'in:初学者,中级,高级'
            ],
            'context.user_profile.goals' => [
                'nullable',
                'array'
            ],
            'context.user_profile.goals.*' => [
                'string',
                'in:减脂,增肌,塑形,力量,耐力,康复,维持'
            ],
            'context.preferences' => [
                'nullable',
                'array'
            ],
            'context.preferences.language' => [
                'nullable',
                'string',
                'in:zh,en'
            ],
            'context.preferences.response_style' => [
                'nullable',
                'string',
                'in:详细,简洁,专业,友好'
            ],
            'context.history' => [
                'nullable',
                'array',
                'max:10' // 最多包含10条历史记录
            ],
            'context.history.*' => [
                'array'
            ],
            'context.history.*.message' => [
                'required_with:context.history.*',
                'string',
                'max:1000'
            ],
            'context.history.*.response' => [
                'required_with:context.history.*',
                'string',
                'max:2000'
            ],
            'context.history.*.timestamp' => [
                'required_with:context.history.*',
                'date'
            ],
            'options' => [
                'nullable',
                'array'
            ],
            'options.include_nutrition' => [
                'nullable',
                'boolean'
            ],
            'options.include_rehabilitation' => [
                'nullable',
                'boolean'
            ],
            'options.response_format' => [
                'nullable',
                'string',
                'in:detailed,summary,quick'
            ],
            'options.max_response_length' => [
                'nullable',
                'integer',
                'min:100',
                'max:5000'
            ],
            'options.include_sources' => [
                'nullable',
                'boolean'
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
            'message.required' => '消息内容不能为空',
            'message.string' => '消息内容必须是字符串',
            'message.max' => '消息内容不能超过2000个字符',
            'message.min' => '消息内容不能为空',
            
            'user_id.required' => '用户ID不能为空',
            'user_id.string' => '用户ID必须是字符串',
            'user_id.max' => '用户ID不能超过100个字符',
            'user_id.regex' => '用户ID格式不正确，只能包含字母、数字、下划线和连字符',
            
            'session_id.string' => '会话ID必须是字符串',
            'session_id.max' => '会话ID不能超过100个字符',
            'session_id.regex' => '会话ID格式不正确，只能包含字母、数字、下划线和连字符',
            
            'context.array' => '上下文信息必须是数组格式',
            'context.user_profile.array' => '用户档案必须是数组格式',
            
            'context.user_profile.age.integer' => '年龄必须是整数',
            'context.user_profile.age.min' => '年龄不能小于10岁',
            'context.user_profile.age.max' => '年龄不能大于100岁',
            
            'context.user_profile.gender.in' => '性别只能是：男、女、其他',
            
            'context.user_profile.weight.numeric' => '体重必须是数字',
            'context.user_profile.weight.min' => '体重不能小于30kg',
            'context.user_profile.weight.max' => '体重不能大于300kg',
            
            'context.user_profile.height.numeric' => '身高必须是数字',
            'context.user_profile.height.min' => '身高不能小于100cm',
            'context.user_profile.height.max' => '身高不能大于250cm',
            
            'context.user_profile.fitness_level.in' => '健身水平只能是：初学者、中级、高级',
            
            'context.user_profile.goals.array' => '健身目标必须是数组格式',
            'context.user_profile.goals.*.in' => '健身目标只能是：减脂、增肌、塑形、力量、耐力、康复、维持',
            
            'context.preferences.array' => '偏好设置必须是数组格式',
            'context.preferences.language.in' => '语言只能是：zh（中文）、en（英文）',
            'context.preferences.response_style.in' => '响应风格只能是：详细、简洁、专业、友好',
            
            'context.history.array' => '历史记录必须是数组格式',
            'context.history.max' => '历史记录最多包含10条',
            
            'options.array' => '选项必须是数组格式',
            'options.include_nutrition.boolean' => '是否包含营养建议必须是布尔值',
            'options.include_rehabilitation.boolean' => '是否包含康复建议必须是布尔值',
            'options.response_format.in' => '响应格式只能是：detailed（详细）、summary（摘要）、quick（快速）',
            'options.max_response_length.integer' => '最大响应长度必须是整数',
            'options.max_response_length.min' => '最大响应长度不能小于100',
            'options.max_response_length.max' => '最大响应长度不能大于5000',
            'options.include_sources.boolean' => '是否包含来源必须是布尔值'
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
            'message' => '消息内容',
            'user_id' => '用户ID',
            'session_id' => '会话ID',
            'context' => '上下文信息',
            'context.user_profile' => '用户档案',
            'context.user_profile.age' => '年龄',
            'context.user_profile.gender' => '性别',
            'context.user_profile.weight' => '体重',
            'context.user_profile.height' => '身高',
            'context.user_profile.fitness_level' => '健身水平',
            'context.user_profile.goals' => '健身目标',
            'context.preferences' => '偏好设置',
            'context.preferences.language' => '语言',
            'context.preferences.response_style' => '响应风格',
            'context.history' => '历史记录',
            'options' => '选项',
            'options.include_nutrition' => '包含营养建议',
            'options.include_rehabilitation' => '包含康复建议',
            'options.response_format' => '响应格式',
            'options.max_response_length' => '最大响应长度',
            'options.include_sources' => '包含来源'
        ];
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