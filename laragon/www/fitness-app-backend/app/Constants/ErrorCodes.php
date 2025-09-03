<?php

namespace App\Constants;

/**
 * 错误码常量类
 * 
 * 定义系统中所有的错误码，便于统一管理和维护
 * 错误码格式：模块_错误类型_具体错误
 * 
 * @author Augment Agent
 * @version 2.0.0
 * @created 2025-08-18 05:25:00
 */
class ErrorCodes
{
    // ========== 通用错误码 ==========
    
    /** 成功 */
    const SUCCESS = 'SUCCESS';
    
    /** 通用错误 */
    const ERROR = 'ERROR';
    
    /** 参数验证失败 */
    const VALIDATION_ERROR = 'VALIDATION_ERROR';
    
    /** 未授权访问 */
    const UNAUTHORIZED = 'UNAUTHORIZED';
    
    /** 权限不足 */
    const FORBIDDEN = 'FORBIDDEN';
    
    /** 资源不存在 */
    const NOT_FOUND = 'NOT_FOUND';
    
    /** 请求方法不允许 */
    const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    
    /** 请求频率限制 */
    const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    
    /** 服务器内部错误 */
    const SERVER_ERROR = 'SERVER_ERROR';
    
    /** 数据库错误 */
    const DATABASE_ERROR = 'DATABASE_ERROR';
    
    /** 缓存错误 */
    const CACHE_ERROR = 'CACHE_ERROR';
    
    // ========== MCP模块错误码 ==========
    
    /** MCP服务不可用 */
    const MCP_SERVICE_UNAVAILABLE = 'MCP_SERVICE_UNAVAILABLE';
    
    /** MCP运动数据不存在 */
    const MCP_EXERCISE_NOT_FOUND = 'MCP_EXERCISE_NOT_FOUND';
    
    /** MCP上下文生成失败 */
    const MCP_CONTEXT_GENERATION_FAILED = 'MCP_CONTEXT_GENERATION_FAILED';
    
    /** MCP推荐生成失败 */
    const MCP_RECOMMENDATION_FAILED = 'MCP_RECOMMENDATION_FAILED';
    
    /** MCP搜索失败 */
    const MCP_SEARCH_FAILED = 'MCP_SEARCH_FAILED';
    
    /** MCP数据格式错误 */
    const MCP_DATA_FORMAT_ERROR = 'MCP_DATA_FORMAT_ERROR';
    
    /** MCP缓存失效 */
    const MCP_CACHE_EXPIRED = 'MCP_CACHE_EXPIRED';
    
    // ========== 用户模块错误码 ==========
    
    /** 用户不存在 */
    const USER_NOT_FOUND = 'USER_NOT_FOUND';
    
    /** 用户已存在 */
    const USER_ALREADY_EXISTS = 'USER_ALREADY_EXISTS';
    
    /** 密码错误 */
    const INVALID_PASSWORD = 'INVALID_PASSWORD';
    
    /** 邮箱未验证 */
    const EMAIL_NOT_VERIFIED = 'EMAIL_NOT_VERIFIED';
    
    /** 账户被锁定 */
    const ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    
    /** 登录令牌无效 */
    const INVALID_TOKEN = 'INVALID_TOKEN';
    
    /** 登录令牌过期 */
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    
    // ========== 营养模块错误码 ==========
    
    /** 营养目标不存在 */
    const NUTRITION_GOAL_NOT_FOUND = 'NUTRITION_GOAL_NOT_FOUND';
    
    /** 营养计划无效 */
    const NUTRITION_PLAN_INVALID = 'NUTRITION_PLAN_INVALID';
    
    /** 营养数据计算错误 */
    const NUTRITION_CALCULATION_ERROR = 'NUTRITION_CALCULATION_ERROR';
    
    /** 食物数据不存在 */
    const FOOD_DATA_NOT_FOUND = 'FOOD_DATA_NOT_FOUND';
    
    // ========== 训练模块错误码 ==========
    
    /** 训练计划不存在 */
    const TRAINING_PLAN_NOT_FOUND = 'TRAINING_PLAN_NOT_FOUND';
    
    /** 训练记录无效 */
    const TRAINING_RECORD_INVALID = 'TRAINING_RECORD_INVALID';
    
    /** 训练数据同步失败 */
    const TRAINING_SYNC_FAILED = 'TRAINING_SYNC_FAILED';
    
    // ========== AI模块错误码 ==========
    
    /** AI服务不可用 */
    const AI_SERVICE_UNAVAILABLE = 'AI_SERVICE_UNAVAILABLE';
    
    /** AI API调用失败 */
    const AI_API_CALL_FAILED = 'AI_API_CALL_FAILED';
    
    /** AI响应格式错误 */
    const AI_RESPONSE_FORMAT_ERROR = 'AI_RESPONSE_FORMAT_ERROR';
    
    /** AI配额不足 */
    const AI_QUOTA_EXCEEDED = 'AI_QUOTA_EXCEEDED';
    
    /** AI模型不支持 */
    const AI_MODEL_NOT_SUPPORTED = 'AI_MODEL_NOT_SUPPORTED';
    
    // ========== AutoGen模块错误码 ==========
    
    /** AutoGen协调员服务不可用 */
    const AUTOGEN_COORDINATOR_UNAVAILABLE = 'AUTOGEN_COORDINATOR_UNAVAILABLE';
    
    /** AutoGen Agent初始化失败 */
    const AUTOGEN_AGENT_INIT_FAILED = 'AUTOGEN_AGENT_INIT_FAILED';
    
    /** AutoGen消息处理失败 */
    const AUTOGEN_MESSAGE_PROCESSING_FAILED = 'AUTOGEN_MESSAGE_PROCESSING_FAILED';
    
    /** AutoGen会话不存在 */
    const AUTOGEN_SESSION_NOT_FOUND = 'AUTOGEN_SESSION_NOT_FOUND';
    
    /** AutoGen Agent协作失败 */
    const AUTOGEN_COLLABORATION_FAILED = 'AUTOGEN_COLLABORATION_FAILED';
    
    /** AutoGen意图识别失败 */
    const AUTOGEN_INTENT_RECOGNITION_FAILED = 'AUTOGEN_INTENT_RECOGNITION_FAILED';
    
    /** AutoGen响应生成失败 */
    const AUTOGEN_RESPONSE_GENERATION_FAILED = 'AUTOGEN_RESPONSE_GENERATION_FAILED';
    
    /** AutoGen质量检查失败 */
    const AUTOGEN_QUALITY_CHECK_FAILED = 'AUTOGEN_QUALITY_CHECK_FAILED';
    
    /** AutoGen MCP连接失败 */
    const AUTOGEN_MCP_CONNECTION_FAILED = 'AUTOGEN_MCP_CONNECTION_FAILED';
    
    /** 服务不可用 */
    const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    
    /** 内部错误 */
    const INTERNAL_ERROR = 'INTERNAL_ERROR';}]}}}
    
    // ========== 文件模块错误码 ==========
    
    /** 文件上传失败 */
    const FILE_UPLOAD_FAILED = 'FILE_UPLOAD_FAILED';
    
    /** 文件类型不支持 */
    const FILE_TYPE_NOT_SUPPORTED = 'FILE_TYPE_NOT_SUPPORTED';
    
    /** 文件大小超限 */
    const FILE_SIZE_EXCEEDED = 'FILE_SIZE_EXCEEDED';
    
    /** 文件不存在 */
    const FILE_NOT_FOUND = 'FILE_NOT_FOUND';
    
    // ========== 支付模块错误码 ==========
    
    /** 支付失败 */
    const PAYMENT_FAILED = 'PAYMENT_FAILED';
    
    /** 订单不存在 */
    const ORDER_NOT_FOUND = 'ORDER_NOT_FOUND';
    
    /** 余额不足 */
    const INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';
    
    /** 支付方式不支持 */
    const PAYMENT_METHOD_NOT_SUPPORTED = 'PAYMENT_METHOD_NOT_SUPPORTED';
    
    // ========== 第三方服务错误码 ==========
    
    /** 短信发送失败 */
    const SMS_SEND_FAILED = 'SMS_SEND_FAILED';
    
    /** 邮件发送失败 */
    const EMAIL_SEND_FAILED = 'EMAIL_SEND_FAILED';
    
    /** 第三方API调用失败 */
    const THIRD_PARTY_API_FAILED = 'THIRD_PARTY_API_FAILED';
    
    /**
     * 获取错误码对应的中文描述
     * 
     * @param string $code 错误码
     * @return string
     */
    public static function getMessage(string $code): string
    {
        $messages = [
            // 通用错误
            self::SUCCESS => '操作成功',
            self::ERROR => '操作失败',
            self::VALIDATION_ERROR => '参数验证失败',
            self::UNAUTHORIZED => '未授权访问',
            self::FORBIDDEN => '权限不足',
            self::NOT_FOUND => '资源不存在',
            self::METHOD_NOT_ALLOWED => '请求方法不允许',
            self::RATE_LIMIT_EXCEEDED => '请求频率超限',
            self::SERVER_ERROR => '服务器内部错误',
            self::DATABASE_ERROR => '数据库操作失败',
            self::CACHE_ERROR => '缓存操作失败',
            
            // MCP模块
            self::MCP_SERVICE_UNAVAILABLE => 'MCP服务暂时不可用',
            self::MCP_EXERCISE_NOT_FOUND => '运动数据不存在',
            self::MCP_CONTEXT_GENERATION_FAILED => '上下文生成失败',
            self::MCP_RECOMMENDATION_FAILED => '推荐生成失败',
            self::MCP_SEARCH_FAILED => '搜索失败',
            self::MCP_DATA_FORMAT_ERROR => 'MCP数据格式错误',
            self::MCP_CACHE_EXPIRED => 'MCP缓存已过期',
            
            // 用户模块
            self::USER_NOT_FOUND => '用户不存在',
            self::USER_ALREADY_EXISTS => '用户已存在',
            self::INVALID_PASSWORD => '密码错误',
            self::EMAIL_NOT_VERIFIED => '邮箱未验证',
            self::ACCOUNT_LOCKED => '账户已被锁定',
            self::INVALID_TOKEN => '登录令牌无效',
            self::TOKEN_EXPIRED => '登录令牌已过期',
            
            // 营养模块
            self::NUTRITION_GOAL_NOT_FOUND => '营养目标不存在',
            self::NUTRITION_PLAN_INVALID => '营养计划无效',
            self::NUTRITION_CALCULATION_ERROR => '营养数据计算错误',
            self::FOOD_DATA_NOT_FOUND => '食物数据不存在',
            
            // 训练模块
            self::TRAINING_PLAN_NOT_FOUND => '训练计划不存在',
            self::TRAINING_RECORD_INVALID => '训练记录无效',
            self::TRAINING_SYNC_FAILED => '训练数据同步失败',
            
            // AI模块
            self::AI_SERVICE_UNAVAILABLE => 'AI服务暂时不可用',
            self::AI_API_CALL_FAILED => 'AI接口调用失败',
            self::AI_RESPONSE_FORMAT_ERROR => 'AI响应格式错误',
            self::AI_QUOTA_EXCEEDED => 'AI使用配额已用完',
            self::AI_MODEL_NOT_SUPPORTED => 'AI模型不支持',
            
            // 文件模块
            self::FILE_UPLOAD_FAILED => '文件上传失败',
            self::FILE_TYPE_NOT_SUPPORTED => '文件类型不支持',
            self::FILE_SIZE_EXCEEDED => '文件大小超出限制',
            self::FILE_NOT_FOUND => '文件不存在',
            
            // 支付模块
            self::PAYMENT_FAILED => '支付失败',
            self::ORDER_NOT_FOUND => '订单不存在',
            self::INSUFFICIENT_BALANCE => '余额不足',
            self::PAYMENT_METHOD_NOT_SUPPORTED => '支付方式不支持',
            
            // 第三方服务
            self::SMS_SEND_FAILED => '短信发送失败',
            self::EMAIL_SEND_FAILED => '邮件发送失败',
            self::THIRD_PARTY_API_FAILED => '第三方服务调用失败'
        ];
        
        return $messages[$code] ?? '未知错误';
    }
    
    /**
     * 检查是否为有效的错误码
     * 
     * @param string $code 错误码
     * @return bool
     */
    public static function isValid(string $code): bool
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        
        return in_array($code, $constants);
    }
    
    /**
     * 获取所有错误码
     * 
     * @return array
     */
    public static function getAllCodes(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return $reflection->getConstants();
    }
    
    /**
     * 根据模块获取错误码
     * 
     * @param string $module 模块名称
     * @return array
     */
    public static function getCodesByModule(string $module): array
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        $moduleCodes = [];
        
        $prefix = strtoupper($module) . '_';
        
        foreach ($constants as $name => $value) {
            if (str_starts_with($value, $prefix)) {
                $moduleCodes[$name] = $value;
            }
        }
        
        return $moduleCodes;
    }
}
