/**
 * 认证状态管理
 * 
 * 使用Pinia管理用户认证状态
 * 遵循汉语命名规范
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { 认证服务 } from '../services/认证服务.js'

/**
 * 认证状态store
 */
export const use认证Store = defineStore('认证', () => {
  // 状态
  const 用户信息 = ref(null)
  const 是否已登录 = ref(false)
  const 是否加载中 = ref(false)
  const 错误信息 = ref('')

  // 计算属性
  const 用户昵称 = computed(() => 用户信息.value?.昵称 || '')
  const 用户邮箱 = computed(() => 用户信息.value?.邮箱 || '')
  const 用户头像 = computed(() => 用户信息.value?.头像 || '')