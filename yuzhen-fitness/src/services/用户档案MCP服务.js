/**
 * MCP用户档案服务 - 与用户档案MCP服务器(端口8003)交互
 * 
 * 主要功能:
 * - 创建用户会话
 * - 更新用户基础信息 
 * - 更新健身目标
 * - 记录训练数据
 * - 获取用户档案
 * - 本地数据同步
 */

import { api } from './api.js'
import { DataMappingService } from './数据映射服务.js'

export class MCPUserProfileService {
  static BASE_URL = '/public/mcp/user-profile'
  static MCP_DIRECT_URL = 'http://localhost:8003/mcp'
  
  /**
   * 直接调用MCP服务器工具
   * @param {string} toolName - 工具名称
   * @param {Object} params - 参数
   * @returns {Promise<Object>} 响应结果
   */
  static async callMCPTool(toolName, params = {}) {
    try {
      console.log(`调用MCP工具: ${toolName}`, params)
      
      // 使用标准化MCP服务器API
      const response = await api.mcp('用户档案', toolName, params)
      
      if (response.data) {
        return response.data
      }
      
      return response
    } catch (error) {
      console.error(`MCP工具调用失败 (${toolName}):`, error)
      throw error  // 不再使用模拟数据，直接抛出错误
    }
  }

  /**
   * 创建用户档案（适配标准化服务器）
   * @param {string} userId - 用户ID
   * @param {Object} profileData - 档案数据
   * @returns {Promise<Object>} 创建结果
   */
  static async createUserProfile(userId, profileData = {}) {
    try {
      console.log('创建用户档案:', { userId, profileData })
      
      // 使用数据映射服务转换格式
      const mcpData = DataMappingService.frontendToMCP({ userId, ...profileData })
      
      const result = await this.callMCPTool('create_user_profile', mcpData)
      
      if (result.success) {
        // 保存到本地存储
        this.saveLocalProfile(userId, profileData)
        console.log('用户档案创建成功:', result)
        return result
      } else {
        throw new Error(result.error || '档案创建失败')
      }
    } catch (error) {
      console.error('创建用户档案失败:', error)
      // 如果MCP失败，至少保存到本地
      this.saveLocalProfile(userId, profileData)
      throw error
    }
  }
  
  /**
   * 记录身体数据
   * @param {string} userId - 用户ID
   * @param {Object} bodyData - 身体数据
   * @returns {Promise<Object>} 记录结果
   */
  static async recordBodyData(userId, bodyData) {
    try {
      console.log('记录身体数据:', { userId, bodyData })
      
      const result = await this.callMCPTool('record_body_data', {
        user_id: userId,
        weight: bodyData.体重,
        body_fat: bodyData.体脂率,
        muscle_mass: bodyData.肌肉量,
        notes: bodyData.备注
      })
      
      if (result.success) {
        // 同步更新本地存储
        const bodyDataRecord = {
          ...bodyData,
          记录时间: new Date().toISOString(),
          id: Date.now().toString()
        }
        this.addLocalBodyDataRecord(userId, bodyDataRecord)
        console.log('身体数据记录成功:', result)
        return result
      } else {
        throw new Error(result.error || '数据记录失败')
      }
    } catch (error) {
      console.error('记录身体数据失败:', error)
      // 如果MCP失败，至少保存到本地
      const bodyDataRecord = {
        ...bodyData,
        记录时间: new Date().toISOString(),
        id: Date.now().toString(),
        synced: false // 标记为未同步
      }
      this.addLocalBodyDataRecord(userId, bodyDataRecord)
      throw error
    }
  }
  
  /**
   * 获取身体数据历史
   * @param {string} userId - 用户ID
   * @param {number} limit - 限制数量
   * @returns {Promise<Array>} 历史数据
   */
  static async getBodyDataHistory(userId, limit = 50) {
    try {
      const result = await this.callMCPTool('get_body_data_history', {
        user_id: userId,
        limit: limit
      })
      
      if (result.success && result.data) {
        // 合并MCP数据和本地数据
        const mcpData = result.data
        const localData = this.getLocalBodyDataHistory(userId, limit)
        
        // 去重并按时间排序
        const combinedData = [...mcpData, ...localData]
        const uniqueData = combinedData.filter((item, index, self) => 
          index === self.findIndex(t => t.id === item.id)
        )
        
        return uniqueData.sort((a, b) => new Date(b.记录时间) - new Date(a.记录时间)).slice(0, limit)
      } else {
        throw new Error(result.error || '获取数据历史失败')
      }
    } catch (error) {
      console.error('获取身体数据历史失败:', error)
      // 降级到本地数据
      return this.getLocalBodyDataHistory(userId, limit)
    }
  }
  
  // 本地存储相关方法省略...
}

// 导出默认实例
export default MCPUserProfileService