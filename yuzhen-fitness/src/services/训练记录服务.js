/**
 * 训练记录服务
 * 负责管理用户的训练记录、统计和数据同步
 */

import { api } from './api.js'

/**
 * 训练记录服务类
 */
export class 训练记录服务 {
  constructor() {
    this.存储键前缀 = 'training_records_'
  }

  /**
   * 保存训练记录到本地存储
   * @param {string} 用户ID 
   * @param {Object} 训练记录 
   */
  保存训练记录(用户ID, 训练记录) {
    try {
      const 存储键 = `training_records_${用户ID}`
      const 现有记录 = this.获取所有训练记录(用户ID)
      
      // 添加记录时间戳和唯一ID
      const 新记录 = {
        id: `training_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
        用户ID,
        训练日期: 训练记录.训练日期 || new Date().toISOString().split('T')[0],
        训练时间: 训练记录.训练时间 || new Date().toISOString(),
        训练类型: 训练记录.训练类型 || '力量训练',
        训练时长: 训练记录.训练时长 || 60, // 分钟
        动作列表: 训练记录.动作列表 || [],
        总组数: 训练记录.总组数 || 0,
        总次数: 训练记录.总次数 || 0,
        消耗卡路里: 训练记录.消耗卡路里 || 0,
        训练强度: 训练记录.训练强度 || '中等',
        训练部位: 训练记录.训练部位 || [],
        备注: 训练记录.备注 || '',
        记录时间: new Date().toISOString()
      }
      
      现有记录.push(新记录)
      
      // 按训练日期排序（最新在前）
      现有记录.sort((a, b) => new Date(b.训练时间) - new Date(a.训练时间))
      
      localStorage.setItem(存储键, JSON.stringify(现有记录))
      console.log('训练记录保存成功:', 新记录)
      
      return 新记录
    } catch (error) {
      console.error('保存训练记录失败:', error)
      throw error
    }
  }

  /**
   * 获取所有训练记录
   * @param {string} 用户ID 
   * @returns {Array}
   */
  获取所有训练记录(用户ID) {
    try {
      const 存储键 = `training_records_${用户ID}`
      const 数据 = localStorage.getItem(存储键)
      return 数据 ? JSON.parse(数据) : []
    } catch (error) {
      console.error('获取训练记录失败:', error)
      return []
    }
  }

  /**
   * 获取训练记录统计数据
   * @param {string} 用户ID 
   * @returns {Object}
   */
  获取训练统计(用户ID) {
    try {
      const 所有记录 = this.获取所有训练记录(用户ID)
      const 现在 = new Date()
      
      // 计算本月记录
      const 本月记录 = 所有记录.filter(记录 => {
        const 记录日期 = new Date(记录.训练时间)
        return 记录日期.getMonth() === 现在.getMonth() && 
               记录日期.getFullYear() === 现在.getFullYear()
      })
      
      // 计算本周记录
      const 本周开始 = new Date(现在)
      本周开始.setDate(现在.getDate() - 现在.getDay())
      本周开始.setHours(0, 0, 0, 0)
      
      const 本周记录 = 所有记录.filter(记录 => {
        const 记录日期 = new Date(记录.训练时间)
        return 记录日期 >= 本周开始
      })
      
      // 计算最近30天记录
      const 三十天前 = new Date(现在)
      三十天前.setDate(现在.getDate() - 30)
      
      const 最近记录 = 所有记录.filter(记录 => {
        const 记录日期 = new Date(记录.训练时间)
        return 记录日期 >= 三十天前
      })
      
      // 计算总训练时长
      const 总时长 = 所有记录.reduce((总计, 记录) => 总计 + (记录.训练时长 || 0), 0)
      const 本月时长 = 本月记录.reduce((总计, 记录) => 总计 + (记录.训练时长 || 0), 0)
      
      return {
        总训练次数: 所有记录.length,
        本月训练次数: 本月记录.length,
        本周训练次数: 本周记录.length,
        最近30天次数: 最近记录.length,
        总训练时长: 总时长,
        本月训练时长: 本月时长,
        平均训练时长: 所有记录.length > 0 ? Math.round(总时长 / 所有记录.length) : 0,
        平均训练强度: '中等',
        最后训练日期: 所有记录.length > 0 ? 所有记录[0].训练日期 : null,
        训练频率: 所有记录.length > 0 ? `每月${本月记录.length}次` : '暂无记录'
      }
    } catch (error) {
      console.error('获取训练统计失败:', error)
      return {
        总训练次数: 0,
        本月训练次数: 0,
        本周训练次数: 0,
        最近30天次数: 0,
        总训练时长: 0,
        本月训练时长: 0,
        平均训练时长: 0,
        平均训练强度: '中等',
        最后训练日期: null,
        训练频率: '暂无记录'
      }
    }
  }

  /**
   * 删除训练记录
   * @param {string} 用户ID 
   * @param {string} 记录ID 
   */
  删除训练记录(用户ID, 记录ID) {
    try {
      const 存储键 = `training_records_${用户ID}`
      const 现有记录 = this.获取所有训练记录(用户ID)
      const 更新记录 = 现有记录.filter(记录 => 记录.id !== 记录ID)
      
      localStorage.setItem(存储键, JSON.stringify(更新记录))
      console.log('训练记录删除成功:', 记录ID)
      
      return true
    } catch (error) {
      console.error('删除训练记录失败:', error)
      throw error
    }
  }
}

// 导出默认实例
export default new 训练记录服务()