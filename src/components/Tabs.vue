<template>
  <div
      ref="root"
      :class="rootClasses">
    <div class="tabs-container">
      <ul class="tab tab-block">
        <li
            v-for="(tab, index) in tabs"
            v-show="!hiddenTabs[index]"
            :key="`jskos-vue-tabs-${index}`"
            class="tab-item"
            :class="{
          'active': tab.isActive,
          'inactive': !tab.isActive,
          'fill': fill,
        }"
            :style="{
          'flex-basis': fill ? fillMinWidth : 'auto',
          'border-bottom-color': activeColor,
        }"
            @click="activateTab(index)">
          <slot
              :tab="tab"
              :index="index"
              name="title">
            <a href="#"> {{ tab.title }}</a>
          </slot>
        </li>
      </ul>
      <div
          ref="tabs"
          class="tab-panes">
        <slot/>
      </div>
    </div>

  </div>
</template>

<script>
import {defineComponent} from "vue"

export default defineComponent({
  name: "Tabs",
  props: {
    /**
     * Index of current tab, use with v-model.
     */
    modelValue: {
      type: Number,
      default: null,
    },
    /**
     * Border color for active tab
     */
    activeColor: {
      type: String,
      default: "red",
    },
    /**
     * If true, the tabs will spread over the available space.
     */
    fill: {
      type: Boolean,
      default: false,
    },
    /**
     * Needed if `flex` is true and it is expected that number of tabs will exceed one row.
     */
    fillMinWidth: {
      type: String,
      default: "0",
    },
    /**
     * If true, borders will be shown. Alternatively, you can provide a string that contains one or more of "top", "right", "bottom", "left" for partial borders.
     *
     * Override the CSS class `jskos-vue-tabs-border-{all|top|right|bottom|left}` to adjust borders.
     */
    borders: {
      type: [Boolean, String],
      default: false,
    },
    /**
     * Size of table. One of `sm`, `md`, `lg`.
     */
    size: {
      type: String,
      default: null,
    },
  },
  emits: ["update:modelValue", "change"],
  data() {
    return {
      activeTab: null,
      activeTabIndex: 0,
      tabs: [],
    }
  },
  computed: {
    _size() {
      if (["sm", "md", "lg"].includes(this.size)) {
        return this.size
      }
      return "md"
    },
    rootClasses() {
      let borderClassPrefix = "jskos-vue-tabs-border-"
      let classes = {
        "jskos-vue-tabs": true,
        [`jskos-vue-tabs-${this._size}`]: true,
      }
      if (this.borders === false) {
        return classes
      }
      if (this.borders === true) {
        classes[`${borderClassPrefix}all`] = true
        return classes
      }
      for (let side of ["top", "right", "bottom", "left"]) {
        if (this.borders.includes(side)) {
          classes[`${borderClassPrefix}${side}`] = true
        }
      }
      return classes
    },
    hiddenTabs() {
      return this.tabs.map(tab => tab.hidden)
    },
  },
  // TODO: For certain changes, all of these watchers apply and call activeTab. It might not be an issue, but it's not efficient.
  watch: {
    tabs(tabs) {
      // Find index of active tab
      let index = tabs.findIndex(tab => this.activeTab == tab)
      index = index == -1 ? (this.modelValue !== null ? this.modelValue : this.activeTabIndex) : index
      this.activateTab(index)
    },
    modelValue(index) {
      this.activateTab(index)
    },
    hiddenTabs() {
      this.activateTab(this.activeTabIndex)
    },
  },
  methods: {
    registerTab(tab) {
      const index = Array.from(this.$refs.tabs.children).indexOf(tab.$el)
      this.tabs = [
        ...this.tabs.slice(0, index),
        tab,
        ...this.tabs.slice(index),
      ]
      if (tab.isActive) {
        this.activateTab(index)
      }
    },
    unregisterTab(tab) {
      this.tabs = this.tabs.filter(t => t != tab)
    },
    activateTab(index) {
      if (this.tabs.length > 0) {
        // Cap index at count of tabs
        if (index >= this.tabs.length) {
          index = this.tabs.length - 1
        }
        // Switch to nearest non-hidden tab
        let diff = 0
        while (index - diff >= 0 || index + diff < this.hiddenTabs.length) {
          if (this.hiddenTabs[index + diff] === false) {
            index = index + diff
            break
          }
          if (this.hiddenTabs[index - diff] === false) {
            index = index - diff
            break
          }
          diff += 1
        }
        // Deactive all tabs
        for (let tab of this.tabs) {
          tab.isActive = false
        }
        let tab = this.tabs[index]
        tab.isActive = true
        if (this.activeTab != tab || this.activeTabIndex != index) {
          this.activeTab = tab
          this.activeTabIndex = index
          this.$emit("update:modelValue", index)
          this.$emit("change", {index, tab})
        }
      }
    },
  },
})
</script>

<style lang="scss">
.tab-panes {
  margin-top: $layout-spacing-sm;
}

@import "spectre.css/src/tabs";

.tab.tab-block {
  display: inline-block;
  margin: 0;
  background: darken($gray-color-light, 2%);
  padding: $unit-2;
  border-radius: $border-radius;

  & > li.tab-item {
    display: inline-block;
    flex: 0;
    margin-bottom: -1px;

    &:first-child {
      padding-left: 0;
    }

    &:last-child {
      padding-right: 0;
    }

    a {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: auto;
      flex-grow: 1;
      height: 100%;
      white-space: nowrap;
      outline: none !important;
      box-shadow: none !important;
      border-bottom: none;
      padding: $unit-2 $unit-6;
    }

    &.active a {
      color: $body-font-color;
      background: $secondary-color;
      border-radius: $border-radius;
    }
  }
}
</style>
