<template>
  <div id="sidebar">
    <div class="logo">
      <img src="../assets/img/logo.svg" alt="DV PAY">
    </div>
    <div class="step-links">
      <a
          href="#"
          :class="stepClasses(step)"
          v-for="(step, i) in steps"
          :key="i"
          v-text="step.name"
          @click.prevent="onClick(step)"
        />
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { useStepsStore } from '@/stores/steps'; // Import your Pinia store

export default {
  name: 'Sidebar',
  setup() {
    const stepsStore = useStepsStore();

    const steps = computed(() => stepsStore.steps);

    function stepClasses(step) {
      const classes = ['step-link'];

      if (step.active) {
        classes.push('active');
      }
      if (step.status === 'locked') {
        classes.push('locked');
      }
      if (step.status === 'failed') {
        classes.push('failed');
      }
      if (step.status === 'complete') {
        classes.push('complete');
      }

      return classes.join(' ');
    }

    const onClick = (step) => {
      if (stepsStore.isLocked(step.id)) {
        return;
      }

      stepsStore.goTo(step.id);
    }

    return {
      steps,
      stepClasses,
      onClick,
    };
  },
};
</script>

<style lang="scss">
#sidebar {
  width: 250px;
  flex-shrink: 0;

  background: $gray-color-light;
  box-shadow: -5px 0 20px rgba(0, 0, 0, 0.08) inset;
  overflow: hidden;
  border-top-left-radius: $border-radius;
  border-bottom-left-radius: $border-radius;

  .logo {
    padding: $layout-spacing 20px;
  }

  .step-links {
    .step-link {
      display: block;
      position: relative;
      padding: $layout-spacing-sm 20px;

      font-family: $heading-font-family;
      font-weight: 500;
      color: $darker-color;
      text-decoration: none;
      opacity: 1;

      &.locked {
        opacity: 0.5;
      }

      &.active {
        margin-right: -1px;

        background: $body-bg;
        box-shadow: 0px 5px 10px rgba(0, 0, 0, 0.08);
        font-weight: 800;
      }

      &.complete {
        color: $success-color;

        &::after {
          content: '✔';
          position: absolute;
          top: 50%;
          right: $layout-spacing-sm;
          width: 24px;
          height: 24px;
          margin-top: -12px;

          background: $success-color;
          border-radius: 50%;
          color: $light-color;
          text-align: center;
          line-height: 24px;
          font-weight: 700;
          font-size: $font-size-lg;
        }

        &.active.complete::after {
          margin-right: 1px;
        }
      }

      &.locked {
        cursor: default;
      }
    }
  }
}
</style>

