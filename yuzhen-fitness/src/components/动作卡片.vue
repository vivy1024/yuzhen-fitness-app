<template>
  <q-card class="动作卡片 cursor-pointer" @click="$emit('click')">
    <!-- 动作图片 -->
    <div class="动作图片容器">
      <q-img
        :src="动作缩略图"
        :alt="动作名称"
        ratio="4/3"
        class="动作图片"
        loading="lazy"
      >
        <!-- 难度标签 -->
        <div class="absolute-top-right q-ma-sm">
          <q-chip :color="难度颜色" text-color="white" size="sm" dense>
            {{ 动作难度 }}
          </q-chip>
        </div>

        <!-- 媒体标识 -->
        <div class="absolute-bottom-left q-ma-sm">
          <div class="媒体标识">
            <q-icon v-if="有视频" name="play_circle" color="white" size="sm" class="q-mr-xs" />
            <q-icon v-if="有图片" name="photo_library" color="white" size="sm" class="q-mr-xs" />
            <span v-if="媒体总数 > 0" class="媒体数量">{{ 媒体总数 }}</span>
          </div>
        </div>
      </q-img>
    </div>

    <!-- 动作信息 -->
    <q-card-section class="动作信息">
      <div class="动作名称">
        <h6 class="q-my-none text-weight-medium">{{ 动作名称 }}</h6>
        <p class="text-caption text-grey-6 q-mb-sm">{{ 英文名称 }}</p>
      </div>

      <!-- 器械类别 -->
      <div class="器械信息 q-mb-sm">
        <q-icon name="fitness_center" size="xs" class="q-mr-xs text-orange-7" />
        <span class="text-caption text-weight-medium text-grey-8">{{ 器械类别 }}</span>
      </div>

      <!-- 主要肌群 -->
      <div class="肌群标签 q-mb-sm">
        <q-chip color="primary" text-color="white" size="sm" dense icon="accessibility">
          {{ 主要肌群 }}
        </q-chip>
      </div>
    </q-card-section>

    <!-- 快速操作 -->
    <q-card-actions class="快速操作" align="around">
      <q-btn flat dense icon="visibility" label="查看" color="primary" size="sm" @click.stop="$emit('click')" />
      <q-btn flat dense icon="add_circle" label="添加" color="positive" size="sm" @click.stop="$emit('add-to-workout')" />
      <q-btn flat dense icon="share" label="分享" color="info" size="sm" @click.stop="$emit('share')" />
    </q-card-actions>
  </q-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  动作数据: {
    type: Object,
    required: true
  }
})

defineEmits(['click', 'add-to-workout', 'share'])

// 计算属性
const 动作名称 = computed(() => 
  props.动作数据?.name_zh || props.动作数据?.name || '未知动作'
)

const 英文名称 = computed(() => 
  props.动作数据?.name || ''
)

const 主要肌群 = computed(() => 
  props.动作数据?.primary_muscle_zh || props.动作数据?.primary_muscle || '未知'
)

const 器械类别 = computed(() => 
  props.动作数据?.equipment_zh || props.动作数据?.equipment || '未知'
)

const 动作难度 = computed(() => 
  props.动作数据?.difficulty?.name_zh || props.动作数据?.difficulty || '中级'
)

const 难度颜色 = computed(() => {
  const 难度 = 动作难度.value
  if (难度.includes('初级') || 难度.includes('Beginner')) return 'green'
  if (难度.includes('中级') || 难度.includes('Intermediate')) return 'orange' 
  if (难度.includes('高级') || 难度.includes('Expert')) return 'red'
  return 'grey'
})

const 动作缩略图 = computed(() => {
  const 图片列表 = props.动作数据?.images || []
  return 图片列表.length > 0 ? 图片列表[0] : '/images/placeholder-exercise.jpg'
})

const 有图片 = computed(() => {
  const 图片列表 = props.动作数据?.images || []
  return 图片列表.length > 0
})

const 有视频 = computed(() => {
  const 视频列表 = props.动作数据?.videos || []
  return 视频列表.length > 0
})

const 媒体总数 = computed(() => {
  const 图片数 = props.动作数据?.images?.length || 0
  const 视频数 = props.动作数据?.videos?.length || 0
  return 图片数 + 视频数
})
</script>

<style scoped>
.动作卡片 {
  transition: transform 0.2s, box-shadow 0.2s;
  border-radius: 12px;
  overflow: hidden;
}

.动作卡片:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.媒体数量 {
  color: white;
  font-size: 12px;
  font-weight: 500;
}
</style>