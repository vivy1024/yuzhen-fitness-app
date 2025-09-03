<template>
  <q-page class="ai-chat-page">
    <!-- 页面标题栏 -->
    <div class="page-header">
      <q-toolbar class="bg-primary text-white">
        <q-toolbar-title class="flex items-center">
          <q-icon name="smart_toy" class="q-mr-sm" size="md" />
          AI 健身助手
        </q-toolbar-title>
        <q-btn 
          flat 
          round 
          icon="refresh" 
          @click="检查服务状态"
          :loading="状态检查中"
        >
          <q-tooltip>检查服务状态</q-tooltip>
        </q-btn>
        <q-btn 
          flat 
          round 
          icon="add" 
          @click="创建新会话"
          :loading="创建会话中"
        >
          <q-tooltip>新建对话</q-tooltip>
        </q-btn>
      </q-toolbar>
    </div>

    <!-- 主要聊天区域 -->
    <div class="chat-container">
      <!-- 消息列表 -->
      <div class="messages-container" ref="消息容器">
        <div class="messages-list">
          <!-- 欢迎消息 -->
          <div v-if="消息列表.length === 0" class="welcome-message">
            <q-card class="welcome-card">
              <q-card-section class="text-center">
                <q-icon name="waving_hand" size="3rem" color="primary" />
                <h5 class="q-mt-md q-mb-sm">欢迎使用 AI 健身助手！</h5>
                <p class="text-grey-7">
                  我是您的专业健身顾问，由多个AI专家组成的团队为您服务：
                </p>
              </q-card-section>
            </q-card>
          </div>

          <!-- 消息气泡 -->
          <div 
            v-for="(消息, 索引) in 消息列表" 
            :key="索引"
            class="message-wrapper"
            :class="消息.类型 === 'user' ? 'user-message' : 'ai-message'"
          >
            <!-- 用户消息 -->
            <div v-if="消息.类型 === 'user'" class="user-bubble">
              <q-chat-message 
                :text="[消息.内容]"
                sent
                :stamp="格式化时间(消息.时间戳)"
                bg-color="primary"
                text-color="white"
              />
            </div>

            <!-- AI 消息 -->
            <div v-else class="ai-bubble">
              <q-chat-message 
                :text="[消息.内容]"
                :stamp="格式化时间(消息.时间戳)"
                bg-color="grey-3"
                text-color="dark"
              />
            </div>
          </div>

          <!-- 加载指示器 -->
          <div v-if="发送中" class="loading-message">
            <q-chat-message bg-color="grey-2">
              <div class="typing-indicator">
                <span>AI 正在思考中</span>
                <q-spinner-dots class="q-ml-sm" />
              </div>
            </q-chat-message>
          </div>
        </div>
      </div>

      <!-- 输入区域 -->
      <div class="input-area">
        <q-form @submit="发送消息" class="input-form">
          <div class="input-container">
            <q-input
              v-model="输入内容"
              placeholder="请输入您的问题..."
              outlined
              autogrow
              :max-height="120"
              class="message-input"
              :disable="发送中"
              @keyup.enter.exact="发送消息"
            >
              <template v-slot:append>
                <q-btn
                  round
                  dense
                  flat
                  icon="send"
                  type="submit"
                  :disable="!输入内容.trim() || 发送中"
                  :loading="发送中"
                  color="primary"
                />
              </template>
            </q-input>
          </div>
        </q-form>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useQuasar } from 'quasar'
import { AutoGen服务 } from '../services/AutoGen服务.js'

const $q = useQuasar()

// 响应式数据
const 消息列表 = ref([])
const 输入内容 = ref('')
const 发送中 = ref(false)
const 状态检查中 = ref(false)
const 创建会话中 = ref(false)
const 消息容器 = ref(null)

// 方法
async function 发送消息() {
  if (!输入内容.value.trim() || 发送中.value) return
  
  const 用户消息 = {
    类型: 'user',
    内容: 输入内容.value,
    时间戳: new Date()
  }
  
  消息列表.value.push(用户消息)
  const 消息内容 = 输入内容.value
  输入内容.value = ''
  
  try {
    发送中.value = true
    
    const 响应 = await AutoGen服务.发送消息({
      消息: 消息内容,
      用户ID: 1,
      会话历史: 消息列表.value.slice(-5)
    })
    
    if (响应.success) {
      消息列表.value.push({
        类型: 'ai',
        内容: 响应.data.response,
        agent_name: 响应.data.agent_type,
        时间戳: new Date()
      })
    }
    
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: '发送失败',
      caption: error.message
    })
  } finally {
    发送中.value = false
  }
}

function 检查服务状态() {
  // 服务状态检查逻辑
}

function 创建新会话() {
  消息列表.value = []
}

function 格式化时间(时间) {
  return new Date(时间).toLocaleTimeString()
}
</script>

<style scoped>
.ai-chat-page {
  height: 100vh;
  display: flex;
  flex-direction: column;
}

.chat-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 16px;
}

.messages-container {
  flex: 1;
  overflow-y: auto;
  margin-bottom: 16px;
}

.welcome-card {
  max-width: 600px;
  margin: 0 auto;
}

.input-area {
  background: white;
  padding: 16px;
  border-radius: 8px;
}

.typing-indicator {
  display: flex;
  align-items: center;
  color: #666;
}
</style>