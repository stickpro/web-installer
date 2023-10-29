<template>
  <div   class="tab-pane" v-show="isActive">
    <slot />
  </div>
</template>

<script>
import { defineComponent } from "vue"

export default defineComponent({
  name: "Tab",
  props: {
    title: {
      type: String,
      default: "Tab",
    },
    active: {
      type: Boolean,
      default: false,
    },
    hidden: {
      type: Boolean,
      default: false,
    },
  },
  data () {
    return {
      isActive: false,
    }
  },
  mounted () {
    this.isActive = this.active
    this.$parent.registerTab(this)
  },
  unmounted () {
    this.$parent.unregisterTab(this)
  },
})
</script>
