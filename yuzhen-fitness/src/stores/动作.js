/**
 * 动作状态管理
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { 动作服务 } from '../services/动作服务.js'

export const use动作Store = defineStore('动作', () => {
  // 状态
  const 动作列表 = ref([])
  const 当前动作 = ref(null)
  const 是否加载中 = ref(false)
  const 搜索关键词 = ref('')
  const 筛选条件 = ref({
    肌群: null,
    器械: [],
    难度: [],
    握法: [],
    机制: [],
    力量: []
  })
  
  const 筛选选项 = ref({
    肌群选项: [],
    器械选项: [],
    难度选项: []
  })
  
  const 分页信息 = ref({
    当前页: 1,
    每页数量: 20,
    总数: 0,
    总页数: 0
  })

  // 计算属性
  const 筛选后的动作列表 = computed(() => {
    let 结果 = 动作列表.value

    // 搜索筛选
    if (搜索关键词.value) {
      const 关键词 = 搜索关键词.value.toLowerCase()
      结果 = 结果.filter(动作 => 
        (动作.name_zh || '').toLowerCase().includes(关键词) ||
        (动作.name || '').toLowerCase().includes(关键词) ||
        (动作.primary_muscle_zh || '').toLowerCase().includes(关键词)
      )
    }

    return 结果
  })

  // 方法
  async function 加载动作列表(参数 = {}) {
    try {
      是否加载中.value = true
      
      const 响应 = await 动作服务.获取动作列表({
        page: 分页信息.value.当前页,
        per_page: 分页信息.value.每页数量,
        search: 搜索关键词.value,
        muscle_group: 筛选条件.value.肌群,
        ...参数
      })
      
      if (响应.success) {
        动作列表.value = 响应.data.exercises || []
        
        // 更新分页信息
        if (响应.data.pagination) {
          分页信息.value = {
            当前页: 响应.data.pagination.current_page,
            每页数量: 响应.data.pagination.per_page,
            总数: 响应.data.pagination.total,
            总页数: 响应.data.pagination.last_page
          }
        }
        
        return { success: true }
      }
      
      throw new Error(响应.message || '加载动作列表失败')
      
    } catch (error) {
      console.error('加载动作列表失败:', error)
      return { success: false, message: error.message }
    } finally {
      是否加载中.value = false
    }
  }

  async function 获取动作详情(动作ID) {
    try {
      是否加载中.value = true
      
      const 响应 = await 动作服务.获取动作详情(动作ID)
      
      if (响应.success) {
        当前动作.value = 响应.data
        return 响应.data
      }
      
      throw new Error(响应.message || '获取动作详情失败')
      
    } catch (error) {
      console.error('获取动作详情失败:', error)
      throw error
    } finally {
      是否加载中.value = false
    }
  }

  function 设置搜索关键词(关键词) {
    搜索关键词.value = 关键词
  }

  function 设置筛选条件(条件) {
    Object.assign(筛选条件.value, 条件)
  }

  async function 初始化() {
    await 加载动作列表()
  }

  return {
    // 状态
    动作列表,
    当前动作,
    是否加载中,
    搜索关键词,
    筛选条件,
    筛选选项,
    分页信息,
    
    // 计算属性
    筛选后的动作列表,
    
    // 方法
    加载动作列表,
    获取动作详情,
    设置搜索关键词,
    设置筛选条件,
    初始化
  }
})