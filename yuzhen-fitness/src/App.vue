<template>
  <router-view />
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { use认证Store } from './stores/认证.js'
import { 初始化性能优化, 清理性能优化资源 } from 'src/utils/性能优化'

// 认证状态管理
const 认证Store = use认证Store()

// 应用启动时初始化认证状态和性能优化
onMounted(() => {
  认证Store.初始化()
  // 初始化性能优化
  初始化性能优化()
})

// 清理性能优化资源
onUnmounted(() => {
  清理性能优化资源()
})
</script>