/**
 * 动作服务
 * 健身动作相关的API调用服务
 */

import { api请求 } from './api.js'

class 动作服务Class {
  constructor() {
    this.基础URL = '/api/exercises'
  }

  /**
   * 获取动作列表
   */
  async 获取动作列表(参数 = {}) {
    try {
      const 响应 = await api请求.get(this.基础URL, { params: 参数 })
      return 响应.data
    } catch (error) {
      console.error('获取动作列表失败:', error)
      throw error
    }
  }

  /**
   * 搜索动作
   */
  async 搜索动作(参数) {
    try {
      const 响应 = await api请求.get(`${this.基础URL}/search`, { params: 参数 })
      return 响应.data
    } catch (error) {
      console.error('搜索动作失败:', error)
      throw error
    }
  }

  /**
   * 获取动作详情
   */
  async 获取动作详情(动作ID) {
    try {
      const 响应 = await api请求.get(`${this.基础URL}/${encodeURIComponent(动作ID)}`)
      return 响应.data
    } catch (error) {
      console.error('获取动作详情失败:', error)
      throw error
    }
  }

  /**
   * 获取肌群列表
   */
  async 获取肌群列表() {
    try {
      const 响应 = await api请求.get(`${this.基础URL}/muscle-groups`)
      return 响应.data
    } catch (error) {
      console.error('获取肌群列表失败:', error)
      throw error
    }
  }

  /**
   * 获取器械列表
   */
  async 获取器械列表() {
    try {
      const 响应 = await api请求.get(`${this.基础URL}/equipment-types`)
      return 响应.data
    } catch (error) {
      console.error('获取器械列表失败:', error)
      throw error
    }
  }

  /**
   * 获取统计信息
   */
  async 获取统计信息() {
    try {
      const 响应 = await api请求.get(`${this.基础URL}/stats`)
      return 响应.data
    } catch (error) {
      console.error('获取统计信息失败:', error)
      throw error
    }
  }
}

// 导出单例实例
export const 动作服务 = new 动作服务Class()
export default 动作服务