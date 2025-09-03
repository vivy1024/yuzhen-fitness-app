<template>
  <div class="登录页面 flex flex-center">
    <div class="登录容器">
      <div class="text-center q-mb-lg">
        <h4 class="text-primary q-my-md">玉珍健身助手</h4>
        <p class="text-grey-6">欢迎回来，开始您的健身之旅</p>
      </div>

      <q-form @submit="处理登录" class="登录表单">
        <q-input
          v-model="登录表单.邮箱"
          type="email"
          label="邮箱地址"
          outlined
          :rules="邮箱验证规则"
          class="q-mb-md"
          prepend-icon="email"
        />

        <q-input
          v-model="登录表单.密码"
          :type="显示密码 ? 'text' : 'password'"
          label="密码"
          outlined
          :rules="密码验证规则"
          class="q-mb-md"
          prepend-icon="lock"
        >
          <template v-slot:append>
            <q-icon
              :name="显示密码 ? 'visibility_off' : 'visibility'"
              class="cursor-pointer"
              @click="显示密码 = !显示密码"
            />
          </template>
        </q-input>

        <q-checkbox v-model="登录表单.记住我" label="记住我" class="q-mb-md" />

        <q-btn
          type="submit"
          label="登录"
          color="primary"
          class="full-width q-mb-md"
          :loading="认证Store.是否加载中"
          :disable="!表单有效"
          size="lg"
        />

        <div class="text-center">
          <q-btn flat label="忘记密码？" color="primary" @click="跳转到密码重置" class="q-mr-md" />
          <q-btn flat label="注册账号" color="secondary" @click="跳转到注册" />
        </div>
      </q-form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { use认证Store } from '../stores/认证.js'

const router = useRouter()
const 认证Store = use认证Store()

const 登录表单 = ref({ 邮箱: '', 密码: '', 记住我: false })
const 显示密码 = ref(false)

const 邮箱验证规则 = [
  val => !!val || '请输入邮箱地址',
  val => /.+@.+\..+/.test(val) || '请输入有效的邮箱地址'
]

const 密码验证规则 = [
  val => !!val || '请输入密码',
  val => val.length >= 6 || '密码至少需要6个字符'
]

const 表单有效 = computed(() => {
  return 登录表单.value.邮箱 && 登录表单.value.密码 && 
         /.+@.+\..+/.test(登录表单.value.邮箱) && 登录表单.value.密码.length >= 6
})

async function 处理登录() {
  const 结果 = await 认证Store.登录(登录表单.value)
  if (结果.success) {
    router.push('/')
  }
}

function 跳转到注册() { router.push('/register') }
function 跳转到密码重置() { router.push('/密码重置') }
</script>

<style scoped>
.登录页面 {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.登录容器 {
  width: 100%; max-width: 400px; padding: 2rem;
  background: white; border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}
</style>