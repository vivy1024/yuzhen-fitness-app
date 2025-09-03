/**
 * API服务配置
 * 统一的HTTP客户端配置
 */

import axios from 'axios'

const API基础URL = process.env.NODE_ENV === 'production'
  ? 'https://api.yuzhen-fitness.com'
  : 'http://localhost:8000'

const api客户端 = axios.create({
  baseURL: `${API基础URL}/api`,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// 请求拦截器
api客户端.interceptors.request.use(
  (config) => {
    const 令牌 = localStorage.getItem('auth_token')
    if (令牌) {
      config.headers.Authorization = `Bearer ${令牌}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// 响应拦截器
api客户端.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user_info')
      window.location.href = '/登录'
    }
    return Promise.reject(error)
  }
)

export const api请求 = {
  get(url, config = {}) {
    return api客户端.get(url, config)
  },
  
  post(url, data = {}, config = {}) {
    return api客户端.post(url, data, config)
  },
  
  put(url, data = {}, config = {}) {
    return api客户端.put(url, data, config)
  },
  
  delete(url, config = {}) {
    return api客户端.delete(url, config)
  }
}

export default api请求