<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    required: false,
    default: 'primary',
  },
  icon: {
    type: String,
    required: true,
  },
  stats: {
    type: String,
    required: true,
  },
  change: {
    type: Number,
    required: false,
    default: 0,
  },
  subtitle: {
    type: String,
    required: false,
    default: '',
  },
})

const isPositive = computed(() => Math.sign(props.change) === 1)
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center">
      <VAvatar
        v-if="props.icon"
        size="40"
        :color="props.color"
        class="elevation-2"
      >
        <VIcon
          :icon="props.icon"
          size="24"
        />
      </VAvatar>
    </VCardText>

    <VCardText>
      <h6 class="text-h6 mb-1">
        {{ props.title }}
      </h6>

      <div class="d-flex align-center mb-1 flex-wrap">
        <h4 class="text-h4 me-2">
          {{ props.stats }}
        </h4>
        <div
          v-if="props.change"
          :class="isPositive ? 'text-success' : 'text-error'"
          class="text-body-1"
        >
          {{ isPositive ? `+${props.change}` : props.change }}%
        </div>
      </div>
      <div v-if="props.subtitle" class="text-body-2">
        {{ props.subtitle }}
      </div>
    </VCardText>
  </VCard>
</template>
