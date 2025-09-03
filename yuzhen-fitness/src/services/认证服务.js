/**
 * 认证服务
 * 用户认证相关的API调用服务
 */

import { api请求 } from './api.js'

class 认证服务Class {
  constructor() {
    this.基础URL = '/api/auth'
    this.TOKEN_KEY = 'auth_token'
    this.USER_KEY = 'user_info'
  }

  /**
   * 用户登录
   */
  async 登录(登录数据) {
    try {
      const 响应 = await api请求.post(`${this.基础URL}/login`, {
        email: 登录数据.邮箱,
        password: 登录数据.密码,
        remember: 登录数据.记住我 || false
      })

      if (响应.data.success && 响应.data.data) {
        // 保存令牌和用户信息
        this.保存令牌(响应.data.data.token)
        this.保存用户信息(响应.data.data.user)
      }

      return 响应.data
    } catch (error) {
      console.error('登录失败:', error)
      throw error
    }
  }

  /**
   * 用户注册
   */
  async 注册(注册数据) {
    try {
      const 响应 = await api请求.post(`${this.基础URL}/register`, {
        name: 注册数据.姓名,
        email: 注册数据.邮箱,
        password: 注册数据.密码,
        password_confirmation: 注册数据.确认密码
      })

      if (响应.data.success && 响应.data.data) {
        this.保存令牌(响应.data.data.token)
        this.保存用户信息(响应.data.data.user)
      }

      return 响应.data
    } catch (error) {
      console.error('注册失败:', error)
      throw error
    }
  }

  /**
   * 用户登出
   */
  async 登出() {
    try {
      await api请求.post(`${this.基础URL}/logout`)
    } catch (error) {
      console.error('登出失败:', error)
    } finally {
      // 无论是否成功，都清除本地存储
      this.清除本地认证信息()
    }
  }

  /**
   * 获取当前用户信息
   */
  async 获取用户信息() {
    try {
      const 响应 = await api请求.get(`${this.基础URL}/me`)
      
      if (响应.data.success && 响应.data.data) {
        this.保存用户信息(响应.data.data.user)
        return 响应.data.data.user
      }
      
      throw new Error('获取用户信息失败')
    } catch (error) {
      console.error('获取用户信息失败:', error)
      throw error
    }
  }

  /**
   * 保存令牌
   */
  保存令牌(token) {
    if (token) {
      localStorage.setItem(this.TOKEN_KEY, token)
    }
  }

  /**
   * 获取令牌
   */
  获取令牌() {
    return localStorage.getItem(this.TOKEN_KEY)
  }

  /**
   * 保存用户信息
   */
  保存用户信息(用户信息) {
    if (用户信息) {
      localStorage.setItem(this.USER_KEY, JSON.stringify(用户信息))
    }
  }

  /**
   * 获取本地用户信息
   */
  获取本地用户信息() {
    try {
      const 用户信息 = localStorage.getItem(this.USER_KEY)
      return 用户信息 ? JSON.parse(用户信息) : null
    } catch (error) {
      console.error('解析用户信息失败:', error)
      return null
    }
  }

  /**
   * 检查认证状态
   */
  检查认证状态() {
    const token = this.获取令牌()
    const 用户信息 = this.获取本地用户信息()
    return !!(token && 用户信息)
  }

  /**
   * 清除本地认证信息
   */
  清除本地认证信息() {
    localStorage.removeItem(this.TOKEN_KEY)
    localStorage.removeItem(this.USER_KEY)
  }
}

// 导出单例实例
export const 认证服务 = new 认证服务Class()
export default 认证服务