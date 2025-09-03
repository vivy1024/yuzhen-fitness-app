<?php

namespace App\Http\Requests\AutoGen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Constants\ErrorCodes;

/**
 * AutoGen专业建议请求验证类
 * 
 * 验证获取健身/营养/康复建议请求的参数
 * 
 * @author Backend Optimization Team
 * @version 1.0.0
 * @created 2025-01-27
 * @updated 2025-01-27
 */
class GetAdviceRequest extends FormRequest
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
            'request_type' => [
                'required',
                'string',
                'in:fitness,nutrition,rehabilitation'
            ],
            'query' => [
                'required',
                'string',
                'max:1000',
                'min:5'
            ],
            'user_profile' => [
                'nullable',
                'array'
            ],
            'user_profile.age' => [
                'nullable',
                'integer',
                'min:10',
                'max:100'
            ],
            'user_profile.gender' => [
                'nullable',
                'string',
                'in:男,女,其他'
            ],
            'user_profile.weight' => [
                'nullable',
                'numeric',
                'min:30',
                'max:300'
            ],
            'user_profile.height' => [
                'nullable',
                'numeric',
                'min:100',
                'max:250'
            ],
            'user_profile.fitness_level' => [
                'nullable',
                'string',
                'in:初学者,中级,高级'
            ],
            'user_profile.goals' => [
                'nullable',
                'array'
            ],
            'user_profile.goals.*' => [
                'string',
                'in:减脂,增肌,塑形,力量,耐力,康复,维持'
            ],
            'user_profile.medical_conditions' => [
                'nullable',
                'array'
            ],
            'user_profile.medical_conditions.*' => [
                'string',
                'max:100'
            ],
            'user_profile.allergies' => [
                'nullable',
                'array'
            ],
            'user_profile.allergies.*' => [
                'string',
                'max:100'
            ],
            'user_profile.dietary_restrictions' => [
                'nullable',
                'array'
            ],
            'user_profile.dietary_restrictions.*' => [
                'string',
                'in:素食,纯素,无麸质,低糖,低盐,低脂,高蛋白,其他'
            ],
            'user_profile.exercise_history' => [
                'nullable',
                'array'
            ],
            'user_profile.exercise_history.years_experience' => [
                'nullable',
                'integer',
                'min:0',
                'max:50'
            ],
            'user_profile.exercise_history.preferred_activities' => [
                'nullable',
                'array'
            ],
            'user_profile.exercise_history.preferred_activities.*' => [
                'string',
                'max:50'
            ],
            'user_profile.exercise_history.injuries' => [
                'nullable',
                'array'
            ],
            'user_profile.exercise_history.injuries.*' => [
                'string',
                'max:200'
            ],
            'context' => [
                'nullable',
                'array'
            ],
            'context.current_plan' => [
                'nullable',
                'array'
            ],
            'context.current_plan.type' => [
                'nullable',
                'string',
                'in:训练计划,营养计划,康复计划'
            ],
            'context.current_plan.duration' => [
                'nullable',
                'integer',
                'min:1',
                'max:365'
            ],
            'context.current_plan.progress' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'context.recent_activities' => [
                'nullable',
                'array',
                'max:10'
            ],
            'context.recent_activities.*' => [
                'array'
            ],
            'context.recent_activities.*.activity' => [
                'required_with:context.recent_activities.*',
                'string',
                'max:100'
            ],
            'context.recent_activities.*.date' => [
                'required_with:context.recent_activities.*',
                'date'
            ],
            'context.recent_activities.*.duration' => [
                'nullable',
                'integer',
                'min:1',
                'max:480' // 最多8小时
            ],
            'context.recent_activities.*.intensity' => [
                'nullable',
                'string',
                'in:低,中,高'
            ],
            'preferences' => [
                'nullable',
                'array'
            ],
            'preferences.detail_level' => [
                'nullable',
                'string',
                'in:简洁,标准,详细'
            ],
            'preferences.include_alternatives' => [
                'nullable',
                'boolean'
            ],
            'preferences.include_scientific_basis' => [
                'nullable',
                'boolean'
            ],
            'preferences.response_language' => [
                'nullable',
                'string',
                'in:zh,en'
            ],
            'session_id' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/'
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
            'user_id.regex' => '用户ID格式不正确',
            
            'request_type.required' => '请求类型不能为空',
            'request_type.in' => '请求类型只能是：fitness（健身）、nutrition（营养）、rehabilitation（康复）',
            
            'query.required' => '查询内容不能为空',
            'query.string' => '查询内容必须是字符串',
            'query.max' => '查询内容不能超过1000个字符',
            'query.min' => '查询内容至少需要5个字符',
            
            'user_profile.array' => '用户档案必须是数组格式',
            'user_profile.age.integer' => '年龄必须是整数',
            'user_profile.age.min' => '年龄不能小于10岁',
            'user_profile.age.max' => '年龄不能大于100岁',
            
            'user_profile.gender.in' => '性别只能是：男、女、其他',
            
            'user_profile.weight.numeric' => '体重必须是数字',
            'user_profile.weight.min' => '体重不能小于30kg',
            'user_profile.weight.max' => '体重不能大于300kg',
            
            'user_profile.height.numeric' => '身高必须是数字',
            'user_profile.height.min' => '身高不能小于100cm',
            'user_profile.height.max' => '身高不能大于250cm',
            
            'user_profile.fitness_level.in' => '健身水平只能是：初学者、中级、高级',
            'user_profile.goals.array' => '健身目标必须是数组格式',
            'user_profile.goals.*.in' => '健身目标只能是：减脂、增肌、塑形、力量、耐力、康复、维持',
            
            'user_profile.medical_conditions.array' => '医疗状况必须是数组格式',
            'user_profile.medical_conditions.*.max' => '每个医疗状况描述不能超过100个字符',
            
            'user_profile.allergies.array' => '过敏信息必须是数组格式',
            'user_profile.allergies.*.max' => '每个过敏信息不能超过100个字符',
            
            'user_profile.dietary_restrictions.array' => '饮食限制必须是数组格式',
            'user_profile.dietary_restrictions.*.in' => '饮食限制只能是：素食、纯素、无麸质、低糖、低盐、低脂、高蛋白、其他',
            
            'context.array' => '上下文信息必须是数组格式',
            'context.current_plan.type.in' => '当前计划类型只能是：训练计划、营养计划、康复计划',
            'context.current_plan.duration.min' => '计划持续时间不能少于1天',
            'context.current_plan.duration.max' => '计划持续时间不能超过365天',
            'context.current_plan.progress.min' => '进度不能小于0%',
            'context.current_plan.progress.max' => '进度不能大于100%',
            
            'context.recent_activities.array' => '最近活动必须是数组格式',
            'context.recent_activities.max' => '最近活动最多包含10条记录',
            
            'preferences.array' => '偏好设置必须是数组格式',
            'preferences.detail_level.in' => '详细程度只能是：简洁、标准、详细',
            'preferences.include_alternatives.boolean' => '是否包含替代方案必须是布尔值',
            'preferences.include_scientific_basis.boolean' => '是否包含科学依据必须是布尔值',
            'preferences.response_language.in' => '响应语言只能是：zh（中文）、en（英文）',
            
            'session_id.string' => '会话ID必须是字符串',
            'session_id.max' => '会话ID不能超过100个字符',
            'session_id.regex' => '会话ID格式不正确'
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
            'request_type' => '请求类型',
            'query' => '查询内容',
            'user_profile' => '用户档案',
            'user_profile.age' => '年龄',
            'user_profile.gender' => '性别',
            'user_profile.weight' => '体重',
            'user_profile.height' => '身高',
            'user_profile.fitness_level' => '健身水平',
            'user_profile.goals' => '健身目标',
            'user_profile.medical_conditions' => '医疗状况',
            'user_profile.allergies' => '过敏信息',
            'user_profile.dietary_restrictions' => '饮食限制',
            'context' => '上下文信息',
            'context.current_plan' => '当前计划',
            'context.recent_activities' => '最近活动',
            'preferences' => '偏好设置',
            'session_id' => '会话ID'
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
