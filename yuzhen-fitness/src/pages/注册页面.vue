<template>
  <div class="注册页面 flex flex-center">
    <div class="注册容器">
      <!-- 应用标题 -->
      <div class="text-center q-mb-lg">
        <h4 class="text-primary q-my-md">加入玉珍健身</h4>
        <p class="text-grey-6">创建账号，开始您的健身之旅</p>
      </div>

      <!-- 注册表单 -->
      <q-form @submit="处理注册" class="注册表单">
        <!-- 昵称输入 -->
        <q-input
          v-model="注册表单.昵称"
          label="昵称"
          outlined
          :rules="昵称验证规则"
          class="q-mb-md"
          prepend-icon="person"
          autocomplete="name"
        />

        <!-- 邮箱输入 -->
        <q-input
          v-model="注册表单.邮箱"
          type="email"
          label="邮箱地址"
          outlined
          :rules="邮箱验证规则"
          class="q-mb-md"
          prepend-icon="email"
          autocomplete="email"
        />

        <!-- 密码输入 -->
        <q-input
          v-model="注册表单.密码"
          :type="显示密码 ? 'text' : 'password'"
          label="密码"
          outlined
          :rules="密码验证规则"
          class="q-mb-md"
          prepend-icon="lock"
          autocomplete="new-password"
        >
          <template v-slot:append>
            <q-icon
              :name="显示密码 ? 'visibility_off' : 'visibility'"
              class="cursor-pointer"
              @click="显示密码 = !显示密码"
            />
          </template>
        </q-input>

        <!-- 确认密码输入 -->
        <q-input
          v-model="注册表单.确认密码"
          :type="显示确认密码 ? 'text' : 'password'"
          label="确认密码"
          outlined
          :rules="确认密码验证规则"
          class="q-mb-md"
          prepend-icon="lock"
          autocomplete="new-password"
        >
          <template v-slot:append>
            <q-icon
              :name="显示确认密码 ? 'visibility_off' : 'visibility'"
              class="cursor-pointer"
              @click="显示确认密码 = !显示确认密码"
            />
          </template>
        </q-input>

        <!-- 性别选择 -->
        <q-select
          v-model="注册表单.性别"
          :options="性别选项"
          label="性别（可选）"
          outlined
          emit-value
          map-options
          class="q-mb-md"
          prepend-icon="wc"
        />

        <!-- 年龄输入 -->
        <q-input
          v-model.number="注册表单.年龄"
          type="number"
          label="年龄（可选）"
          outlined
          :rules="年龄验证规则"
          class="q-mb-md"
          prepend-icon="cake"
          min="13"
          max="100"
        />

        <!-- 错误信息显示 -->
        <q-banner
          v-if="认证Store.错误信息"
          class="text-negative q-mb-md"
          dense
          rounded
        >
          {{ 认证Store.错误信息 }}
        </q-banner>

        <!-- 注册按钮 -->
        <q-btn
          type="submit"
          label="注册"
          color="primary"
          class="full-width q-mb-md"
          :loading="认证Store.是否加载中"
          :disable="!表单有效"
          size="lg"
        />

        <!-- 分割线 -->
        <q-separator class="q-my-lg" />

        <!-- 其他操作 -->
        <div class="text-center">
          <span class="text-grey-6">已有账号？</span>
          <q-btn
            flat
            label="立即登录"
            color="primary"
            @click="跳转到登录"
            class="q-ml-sm"
          />
        </div>
      </q-form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { use认证Store } from '../stores/认证.js'

// 路由和工具
const router = useRouter()
const $q = useQuasar()
const 认证Store = use认证Store()

// 响应式数据
const 注册表单 = ref({
  昵称: '',
  邮箱: '',
  密码: '',
  确认密码: '',
  性别: '',
  年龄: null
})

const 显示密码 = ref(false)
const 显示确认密码 = ref(false)

// 选项数据
const 性别选项 = [
  { label: '男', value: 'male' },
  { label: '女', value: 'female' },
  { label: '其他', value: 'other' }
]

// 表单验证规则
const 昵称验证规则 = [
  val => !!val || '请输入昵称',
  val => val.length >= 2 || '昵称至少需要2个字符'
]

const 邮箱验证规则 = [
  val => !!val || '请输入邮箱地址',
  val => /.+@.+\..+/.test(val) || '请输入有效的邮箱地址'
]

const 密码验证规则 = [
  val => !!val || '请输入密码',
  val => val.length >= 6 || '密码至少需要6个字符',
  val => /(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(val) || '密码需要包含大小写字母和数字'
]

const 确认密码验证规则 = [
  val => !!val || '请确认密码',
  val => val === 注册表单.value.密码 || '两次输入的密码不一致'
]

const 年龄验证规则 = [
  val => val === null || val === '' || (val >= 13 && val <= 100) || '年龄必须在13-100岁之间'
]

// 计算属性
const 表单有效 = computed(() => {
  return 注册表单.value.昵称 && 
         注册表单.value.邮箱 && 
         注册表单.value.密码 && 
         注册表单.value.确认密码 &&
         注册表单.value.昵称.length >= 2 &&
         /.+@.+\..+/.test(注册表单.value.邮箱) &&
         注册表单.value.密码.length >= 6 &&
         /(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(注册表单.value.密码) &&
         注册表单.value.密码 === 注册表单.value.确认密码
})

// 方法
/**
 * 处理注册提交
 */
async function 处理注册() {
  // 清除之前的错误信息
  认证Store.清除错误()
  
  const 结果 = await 认证Store.注册(注册表单.value)
  
  if (结果.success) {
    $q.notify({
      type: 'positive',
      message: '注册成功！欢迎加入玉珍健身',
      position: 'top'
    })
    
    // 跳转到首页
    router.push('/')
  } else {
    $q.notify({
      type: 'negative',
      message: 结果.message,
      position: 'top'
    })
  }
}

/**
 * 跳转到登录页面
 */
function 跳转到登录() {
  router.push('/login')
}

// 生命周期
onMounted(() => {
  // 如果已经登录，直接跳转到首页
  if (认证Store.是否已登录) {
    router.push('/')
  }
})
</script>

<style lang="scss" scoped>
.注册页面 {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.注册容器 {
  width: 100%;
  max-width: 450px;
  padding: 2rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.注册表单 {
  width: 100%;
}

@media (max-width: 600px) {
  .注册容器 {
    margin: 1rem;
    padding: 1.5rem;
  }
}
</style>